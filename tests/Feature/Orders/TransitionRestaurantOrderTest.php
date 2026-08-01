<?php

use App\Actions\Orders\TransitionRestaurantOrderAction;
use App\Enums\RestaurantOrderStatus;
use App\Enums\RestaurantRole;
use App\Events\RestaurantOrderStatusChanged;
use App\Exceptions\InvalidRestaurantOrderTransitionException;
use App\Models\Restaurant;
use App\Models\RestaurantOrder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Lunar\Models\Currency;
use Lunar\Models\Language;

beforeEach(function () {
    Language::factory()->create(['code' => 'en', 'default' => true]);
    Currency::factory()->create(['default' => true]);

    $this->restaurant = Restaurant::factory()->create();
    $this->staff = User::factory()->create();
    $this->restaurant->members()->attach($this->staff, ['role' => RestaurantRole::Staff->value]);
});

test('a sub-order moves through the fulfilment lifecycle stamping each timestamp', function () {
    $subOrder = RestaurantOrder::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $action = new TransitionRestaurantOrderAction;

    $subOrder = $action->handle($this->staff, $subOrder, RestaurantOrderStatus::PaymentReceived);
    expect($subOrder->status)->toBe(RestaurantOrderStatus::PaymentReceived)
        ->and($subOrder->placed_at)->not->toBeNull();

    $subOrder = $action->handle($this->staff, $subOrder, RestaurantOrderStatus::Accepted);
    expect($subOrder->status)->toBe(RestaurantOrderStatus::Accepted)
        ->and($subOrder->accepted_at)->not->toBeNull();

    $subOrder = $action->handle($this->staff, $subOrder, RestaurantOrderStatus::Preparing);
    expect($subOrder->status)->toBe(RestaurantOrderStatus::Preparing)
        ->and($subOrder->preparing_at)->not->toBeNull();

    $subOrder = $action->handle($this->staff, $subOrder, RestaurantOrderStatus::Dispatched);
    expect($subOrder->status)->toBe(RestaurantOrderStatus::Dispatched)
        ->and($subOrder->dispatched_at)->not->toBeNull();

    $subOrder = $action->handle($this->staff, $subOrder, RestaurantOrderStatus::Completed);
    expect($subOrder->status)->toBe(RestaurantOrderStatus::Completed)
        ->and($subOrder->completed_at)->not->toBeNull()
        ->and($subOrder->isTerminal())->toBeTrue();
});

test('invalid transitions are rejected', function (RestaurantOrderStatus $from, RestaurantOrderStatus $to) {
    $subOrder = RestaurantOrder::factory()->create([
        'restaurant_id' => $this->restaurant->id,
        'status' => $from,
        'paused_from_status' => $from === RestaurantOrderStatus::OnHold ? 'preparing' : null,
    ]);

    (new TransitionRestaurantOrderAction)->handle($this->staff, $subOrder, $to);
})->with([
    'pending skips payment' => [RestaurantOrderStatus::Pending, RestaurantOrderStatus::Accepted],
    'pending completes' => [RestaurantOrderStatus::Pending, RestaurantOrderStatus::Completed],
    'payment received skips acceptance' => [RestaurantOrderStatus::PaymentReceived, RestaurantOrderStatus::Preparing],
    'accepted skips preparation' => [RestaurantOrderStatus::Accepted, RestaurantOrderStatus::Dispatched],
    'preparing completes' => [RestaurantOrderStatus::Preparing, RestaurantOrderStatus::Completed],
    'dispatched is cancelled' => [RestaurantOrderStatus::Dispatched, RestaurantOrderStatus::Cancelled],
    'pending goes on hold' => [RestaurantOrderStatus::Pending, RestaurantOrderStatus::OnHold],
    'completed leaves terminal' => [RestaurantOrderStatus::Completed, RestaurantOrderStatus::Dispatched],
    'cancelled leaves terminal' => [RestaurantOrderStatus::Cancelled, RestaurantOrderStatus::Accepted],
    'refunded leaves terminal' => [RestaurantOrderStatus::Refunded, RestaurantOrderStatus::Completed],
])->throws(InvalidRestaurantOrderTransitionException::class);

test('transitions dispatch a status-changed event with the actor and both statuses', function () {
    $subOrder = RestaurantOrder::factory()->paymentReceived()->create(['restaurant_id' => $this->restaurant->id]);

    Event::fake([RestaurantOrderStatusChanged::class]);

    (new TransitionRestaurantOrderAction)->handle($this->staff, $subOrder, RestaurantOrderStatus::Accepted);

    Event::assertDispatched(
        RestaurantOrderStatusChanged::class,
        fn (RestaurantOrderStatusChanged $event): bool => $event->restaurantOrder->is($subOrder)
            && $event->actor->is($this->staff)
            && $event->from === RestaurantOrderStatus::PaymentReceived
            && $event->to === RestaurantOrderStatus::Accepted
    );
});

test('outsiders cannot transition sub-orders', function () {
    $outsider = User::factory()->create();
    $subOrder = RestaurantOrder::factory()->create(['restaurant_id' => $this->restaurant->id]);

    (new TransitionRestaurantOrderAction)->handle($outsider, $subOrder, RestaurantOrderStatus::PaymentReceived);
})->throws(AuthorizationException::class);
