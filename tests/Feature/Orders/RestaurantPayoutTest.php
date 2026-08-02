<?php

use App\Actions\Orders\TransitionRestaurantOrderAction;
use App\Enums\RestaurantOrderStatus;
use App\Enums\RestaurantRole;
use App\Events\RestaurantOrderStatusChanged;
use App\Models\CommissionTier;
use App\Models\Lunar\Order;
use App\Models\Restaurant;
use App\Models\RestaurantOrder;
use App\Models\RestaurantPayout;
use App\Models\User;
use Database\Seeders\CommissionTierSeeder;
use Lunar\Models\Currency;
use Lunar\Models\Language;

beforeEach(function () {
    Language::factory()->create(['code' => 'en', 'default' => true]);
    Currency::factory()->create(['default' => true]);
    $this->seed(CommissionTierSeeder::class);

    $this->restaurant = Restaurant::factory()->create();
    $this->staff = User::factory()->create();
    $this->restaurant->members()->attach($this->staff, ['role' => RestaurantRole::Staff->value]);

    $this->makeDispatchedSubOrder = function (int $total = 5000, ?int $commissionRate = null): RestaurantOrder {
        if ($commissionRate !== null) {
            $this->restaurant->commission_rate = $commissionRate;
            $this->restaurant->save();
        }

        return RestaurantOrder::factory()->create([
            'order_id' => Order::factory()->create()->id,
            'restaurant_id' => $this->restaurant->id,
            'status' => RestaurantOrderStatus::Dispatched,
            'sub_total' => $total,
            'total' => $total,
        ]);
    };
});

test('completing a sub-order records an escrowed payout with the default commission', function () {
    $subOrder = ($this->makeDispatchedSubOrder)(5000);

    app(TransitionRestaurantOrderAction::class)->handle($this->staff, $subOrder, RestaurantOrderStatus::Completed);

    $payout = RestaurantPayout::sole();
    expect($payout->restaurant_order_id)->toBe($subOrder->id)
        ->and($payout->restaurant_id)->toBe($this->restaurant->id)
        ->and($payout->gross_amount)->toBe(5000)
        ->and($payout->commission_amount)->toBe(750)
        ->and($payout->net_amount)->toBe(4250)
        ->and($payout->status)->toBe('pending')
        ->and($payout->eligible_at->isSameDay($subOrder->refresh()->completed_at->addDays(30)))->toBeTrue();
});

test('a custom commission rate override is honored', function () {
    $subOrder = ($this->makeDispatchedSubOrder)(5000, 2000);

    app(TransitionRestaurantOrderAction::class)->handle($this->staff, $subOrder, RestaurantOrderStatus::Completed);

    $payout = RestaurantPayout::sole();
    expect($payout->commission_amount)->toBe(1000)
        ->and($payout->net_amount)->toBe(4000);
});

test('a preferred tier restaurant pays the tier rate', function () {
    $this->restaurant->commission_tier_id = CommissionTier::where('slug', 'preferred')->firstOrFail()->id;
    $this->restaurant->save();
    $subOrder = ($this->makeDispatchedSubOrder)(5000);

    app(TransitionRestaurantOrderAction::class)->handle($this->staff, $subOrder, RestaurantOrderStatus::Completed);

    $payout = RestaurantPayout::sole();
    expect($payout->commission_amount)->toBe(500)
        ->and($payout->net_amount)->toBe(4500);
});

test('marketplace-owned sub-orders record no payout', function () {
    $subOrder = RestaurantOrder::factory()->marketplaceOwned()->create([
        'order_id' => Order::factory()->create()->id,
        'status' => RestaurantOrderStatus::Dispatched,
        'total' => 5000,
    ]);
    $admin = User::factory()->admin()->create();

    app(TransitionRestaurantOrderAction::class)->handle($admin, $subOrder, RestaurantOrderStatus::Completed);

    expect(RestaurantPayout::count())->toBe(0);
});

test('payout records are created only once per sub-order', function () {
    $subOrder = ($this->makeDispatchedSubOrder)(5000);

    RestaurantOrderStatusChanged::dispatch($subOrder, $this->staff, RestaurantOrderStatus::Dispatched, RestaurantOrderStatus::Completed);
    RestaurantOrderStatusChanged::dispatch($subOrder, $this->staff, RestaurantOrderStatus::Dispatched, RestaurantOrderStatus::Completed);

    expect(RestaurantPayout::count())->toBe(1);
});

test('the eligible scope surfaces only matured pending payouts', function () {
    $eligible = RestaurantPayout::factory()->eligible()->create();
    RestaurantPayout::factory()->create();
    RestaurantPayout::factory()->eligible()->paid()->create();

    expect(RestaurantPayout::eligible()->sole()->id)->toBe($eligible->id);
});

test('markPaid settles the payout', function () {
    $payout = RestaurantPayout::factory()->eligible()->create();

    $payout->markPaid();

    expect($payout->status)->toBe('paid')
        ->and($payout->paid_at)->not->toBeNull()
        ->and(RestaurantPayout::eligible()->count())->toBe(0);
});
