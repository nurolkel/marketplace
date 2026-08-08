<?php

use App\Actions\Orders\TransitionRestaurantOrderAction;
use App\Actions\Restaurants\AssignRestaurantPromotionAction;
use App\Enums\RestaurantOrderStatus;
use App\Enums\RestaurantRole;
use App\Enums\UserType;
use App\Events\RestaurantOrderStatusChanged;
use App\Models\CommissionPromotion;
use App\Models\CommissionTier;
use App\Models\Lunar\Order;
use App\Models\Restaurant;
use App\Models\RestaurantOrder;
use App\Models\RestaurantPayout;
use App\Models\User;
use Database\Seeders\CommissionPromotionSeeder;
use Database\Seeders\CommissionTierSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Lunar\Models\Currency;
use Lunar\Models\Language;

beforeEach(function () {
    Language::factory()->create(['code' => 'en', 'default' => true]);
    Currency::factory()->create(['default' => true]);
    $this->seed(CommissionTierSeeder::class);

    $this->restaurant = Restaurant::factory()->create();
    $this->owner = User::factory()->create();
    $this->restaurant->members()->attach($this->owner, ['role' => RestaurantRole::Owner->value]);
    $this->admin = User::factory()->create(['type' => UserType::Admin]);

    $this->completeSubOrder = function (int $total = 5000): RestaurantOrder {
        $subOrder = RestaurantOrder::factory()->create([
            'order_id' => Order::factory()->create()->id,
            'restaurant_id' => $this->restaurant->id,
            'status' => RestaurantOrderStatus::Dispatched,
            'sub_total' => $total,
            'total' => $total,
        ]);

        app(TransitionRestaurantOrderAction::class)
            ->handle($this->owner, $subOrder, RestaurantOrderStatus::Completed);

        return $subOrder;
    };
});

test('only platform admins can assign promotions', function () {
    $promotion = CommissionPromotion::factory()->firstDays(30)->create();

    app(AssignRestaurantPromotionAction::class)
        ->handle($this->admin, $this->restaurant, $promotion);

    expect($this->restaurant->refresh()->activeCommissionPromotion())->not->toBeNull();

    app(AssignRestaurantPromotionAction::class)
        ->handle($this->owner, $this->restaurant, $promotion);
})->throws(AuthorizationException::class);

test('assignment starts now, ends after the promotion duration, and replaces any previous one', function () {
    $first = CommissionPromotion::factory()->firstDays(30)->create();
    $second = CommissionPromotion::factory()->firstOrders(100)->create();
    $action = app(AssignRestaurantPromotionAction::class);

    $action->handle($this->admin, $this->restaurant, $first);

    $assignment = $this->restaurant->refresh()->commissionPromotions->first()->pivot;
    expect($assignment->starts_at->isToday())->toBeTrue()
        ->and($assignment->ends_at->isSameDay(now()->addDays(30)))->toBeTrue()
        ->and($assignment->orders_used)->toBe(0);

    $action->handle($this->admin, $this->restaurant, $second);

    $assignments = $this->restaurant->refresh()->commissionPromotions;
    expect($assignments)->toHaveCount(1)
        ->and($assignments->first()->id)->toBe($second->id)
        ->and($assignments->first()->pivot->ends_at)->toBeNull();
});

test('the seeder provides the launch promotions', function () {
    $this->seed(CommissionPromotionSeeder::class);

    expect(CommissionPromotion::count())->toBe(3)
        ->and(CommissionPromotion::where('slug', 'first-30-days-free')->value('duration_days'))->toBe(30)
        ->and(CommissionPromotion::where('slug', 'first-100-orders-free')->value('max_orders'))->toBe(100)
        ->and(CommissionPromotion::where('slug', 'half-commission-60-days')->value('rate'))->toBe(750);
});

test('an active promotion beats the tier and any custom override', function () {
    $this->restaurant->update([
        'commission_tier_id' => CommissionTier::where('slug', 'preferred')->firstOrFail()->id,
        'commission_rate' => 300,
    ]);
    $promotion = CommissionPromotion::factory()->firstDays(30)->withRate(50)->create();

    app(AssignRestaurantPromotionAction::class)->handle($this->admin, $this->restaurant, $promotion);

    expect($this->restaurant->refresh()->effectiveCommissionRate())->toBe(50);
});

test('an expired schedule returns the restaurant to its normal rate', function () {
    $promotion = CommissionPromotion::factory()->firstDays(30)->create();
    app(AssignRestaurantPromotionAction::class)->handle($this->admin, $this->restaurant, $promotion);

    expect($this->restaurant->refresh()->effectiveCommissionRate())->toBe(0);

    $this->travel(31)->days();

    expect($this->restaurant->refresh()->activeCommissionPromotion())->toBeNull()
        ->and($this->restaurant->effectiveCommissionRate())->toBe(1500);
});

test('hitting the order cap ends the promotion', function () {
    $promotion = CommissionPromotion::factory()->firstOrders(1)->create();
    app(AssignRestaurantPromotionAction::class)->handle($this->admin, $this->restaurant, $promotion);

    ($this->completeSubOrder)();

    expect($this->restaurant->refresh()->activeCommissionPromotion())->toBeNull()
        ->and($this->restaurant->effectiveCommissionRate())->toBe(1500);
});

test('an inactive promotion never applies', function () {
    $promotion = CommissionPromotion::factory()->firstDays(30)->create(['active' => false]);
    app(AssignRestaurantPromotionAction::class)->handle($this->admin, $this->restaurant, $promotion);

    expect($this->restaurant->refresh()->activeCommissionPromotion())->toBeNull()
        ->and($this->restaurant->effectiveCommissionRate())->toBe(1500);
});

test('a commission-free promotion zeros the payout commission and counts the order', function () {
    $promotion = CommissionPromotion::factory()->firstOrders(100)->create();
    app(AssignRestaurantPromotionAction::class)->handle($this->admin, $this->restaurant, $promotion);

    ($this->completeSubOrder)(5000);

    $payout = RestaurantPayout::sole();
    expect($payout->commission_amount)->toBe(0)
        ->and($payout->net_amount)->toBe(5000)
        ->and($this->restaurant->refresh()->commissionPromotions->first()->pivot->orders_used)->toBe(1);
});

test('a reduced-rate promotion is honored in the payout', function () {
    $promotion = CommissionPromotion::factory()->firstDays(30)->withRate(750)->create();
    app(AssignRestaurantPromotionAction::class)->handle($this->admin, $this->restaurant, $promotion);

    ($this->completeSubOrder)(5000);

    $payout = RestaurantPayout::sole();
    expect($payout->commission_amount)->toBe(375)
        ->and($payout->net_amount)->toBe(4625);
});

test('the order counter ignores repeated completion events', function () {
    $promotion = CommissionPromotion::factory()->firstOrders(100)->create();
    app(AssignRestaurantPromotionAction::class)->handle($this->admin, $this->restaurant, $promotion);

    $subOrder = ($this->completeSubOrder)();

    RestaurantOrderStatusChanged::dispatch(
        $subOrder, $this->owner, RestaurantOrderStatus::Dispatched, RestaurantOrderStatus::Completed,
    );

    expect($this->restaurant->refresh()->commissionPromotions->first()->pivot->orders_used)->toBe(1)
        ->and(RestaurantPayout::count())->toBe(1);
});

test('orders after the cap pay the normal rate', function () {
    $promotion = CommissionPromotion::factory()->firstOrders(1)->create();
    app(AssignRestaurantPromotionAction::class)->handle($this->admin, $this->restaurant, $promotion);

    ($this->completeSubOrder)();
    ($this->completeSubOrder)();

    expect(RestaurantPayout::orderBy('id')->pluck('commission_amount')->all())->toBe([0, 750]);
});
