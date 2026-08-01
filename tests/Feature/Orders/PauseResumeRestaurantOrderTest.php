<?php

use App\Actions\Orders\PauseRestaurantOrderAction;
use App\Actions\Orders\ResumeRestaurantOrderAction;
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

test('pausing an accepted sub-order puts it on hold with the reason recorded', function () {
    $subOrder = RestaurantOrder::factory()->accepted()->create(['restaurant_id' => $this->restaurant->id]);

    Event::fake([RestaurantOrderStatusChanged::class]);

    $subOrder = (new PauseRestaurantOrderAction)->handle($this->staff, $subOrder, 'Waiting on a freezer delivery');

    expect($subOrder->status)->toBe(RestaurantOrderStatus::OnHold)
        ->and($subOrder->paused_from_status)->toBe(RestaurantOrderStatus::Accepted->value)
        ->and($subOrder->pause_reason)->toBe('Waiting on a freezer delivery')
        ->and($subOrder->paused_at)->not->toBeNull();

    Event::assertDispatched(
        RestaurantOrderStatusChanged::class,
        fn (RestaurantOrderStatusChanged $event): bool => $event->from === RestaurantOrderStatus::Accepted
            && $event->to === RestaurantOrderStatus::OnHold
    );
});

test('pausing a preparing sub-order keeps preparing as the resume target', function () {
    $subOrder = RestaurantOrder::factory()->preparing()->create(['restaurant_id' => $this->restaurant->id]);

    $subOrder = (new PauseRestaurantOrderAction)->handle($this->staff, $subOrder, 'Courier delayed');

    expect($subOrder->status)->toBe(RestaurantOrderStatus::OnHold)
        ->and($subOrder->paused_from_status)->toBe(RestaurantOrderStatus::Preparing->value);
});

test('sub-orders outside active fulfilment cannot be paused', function (RestaurantOrderStatus $status) {
    $subOrder = RestaurantOrder::factory()->create([
        'restaurant_id' => $this->restaurant->id,
        'status' => $status,
        'paused_from_status' => $status === RestaurantOrderStatus::OnHold ? 'preparing' : null,
    ]);

    (new PauseRestaurantOrderAction)->handle($this->staff, $subOrder, 'Not allowed');
})->with([
    'pending' => [RestaurantOrderStatus::Pending],
    'payment received' => [RestaurantOrderStatus::PaymentReceived],
    'already on hold' => [RestaurantOrderStatus::OnHold],
    'dispatched' => [RestaurantOrderStatus::Dispatched],
    'completed' => [RestaurantOrderStatus::Completed],
])->throws(InvalidRestaurantOrderTransitionException::class);

test('a pause reason is required', function () {
    $subOrder = RestaurantOrder::factory()->preparing()->create(['restaurant_id' => $this->restaurant->id]);

    (new PauseRestaurantOrderAction)->handle($this->staff, $subOrder, '   ');
})->throws(InvalidArgumentException::class);

test('outsiders cannot pause sub-orders', function () {
    $outsider = User::factory()->create();
    $subOrder = RestaurantOrder::factory()->preparing()->create(['restaurant_id' => $this->restaurant->id]);

    (new PauseRestaurantOrderAction)->handle($outsider, $subOrder, 'Trying to pause');
})->throws(AuthorizationException::class);

test('resuming restores the paused-from status and clears the pause fields', function (RestaurantOrderStatus $pausedFrom) {
    $subOrder = RestaurantOrder::factory()->onHold($pausedFrom)->create(['restaurant_id' => $this->restaurant->id]);

    Event::fake([RestaurantOrderStatusChanged::class]);

    $subOrder = (new ResumeRestaurantOrderAction)->handle($this->staff, $subOrder);

    expect($subOrder->status)->toBe($pausedFrom)
        ->and($subOrder->paused_from_status)->toBeNull()
        ->and($subOrder->pause_reason)->toBeNull()
        ->and($subOrder->paused_at)->toBeNull();

    Event::assertDispatched(
        RestaurantOrderStatusChanged::class,
        fn (RestaurantOrderStatusChanged $event): bool => $event->from === RestaurantOrderStatus::OnHold
            && $event->to === $pausedFrom
    );
})->with([
    'accepted' => [RestaurantOrderStatus::Accepted],
    'preparing' => [RestaurantOrderStatus::Preparing],
]);

test('sub-orders that are not on hold cannot be resumed', function () {
    $subOrder = RestaurantOrder::factory()->preparing()->create(['restaurant_id' => $this->restaurant->id]);

    (new ResumeRestaurantOrderAction)->handle($this->staff, $subOrder);
})->throws(InvalidRestaurantOrderTransitionException::class);

test('outsiders cannot resume sub-orders', function () {
    $outsider = User::factory()->create();
    $subOrder = RestaurantOrder::factory()->onHold()->create(['restaurant_id' => $this->restaurant->id]);

    (new ResumeRestaurantOrderAction)->handle($outsider, $subOrder);
})->throws(AuthorizationException::class);
