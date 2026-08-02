<?php

use App\Actions\Orders\CancelRestaurantOrderAction;
use App\Enums\RestaurantOrderStatus;
use App\Enums\RestaurantRole;
use App\Enums\UserType;
use App\Models\Lunar\Order;
use App\Models\Restaurant;
use App\Models\RestaurantOrder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Lunar\Models\Currency;
use Lunar\Models\Language;

beforeEach(function () {
    Language::factory()->create(['code' => 'en', 'default' => true]);
    Currency::factory()->create(['default' => true]);

    $this->customer = User::factory()->create();
    $this->restaurantA = Restaurant::factory()->create();
    $this->restaurantB = Restaurant::factory()->create();
    $this->staffA = User::factory()->create();
    $this->restaurantA->members()->attach($this->staffA, ['role' => RestaurantRole::Staff->value]);

    /**
     * One checkout spanning two restaurants: a parent order with a
     * preparing sub-order for each, so one side can be cancelled
     * while the other keeps moving.
     *
     * @return array{Order, RestaurantOrder, RestaurantOrder}
     */
    $this->makeTwoRestaurantOrder = function (): array {
        $order = Order::factory()->create([
            'user_id' => $this->customer->id,
            'status' => 'payment-received',
        ]);

        $subA = RestaurantOrder::factory()->create([
            'order_id' => $order->id,
            'restaurant_id' => $this->restaurantA->id,
            'status' => RestaurantOrderStatus::Preparing,
        ]);
        $subB = RestaurantOrder::factory()->create([
            'order_id' => $order->id,
            'restaurant_id' => $this->restaurantB->id,
            'status' => RestaurantOrderStatus::Preparing,
        ]);

        return [$order, $subA, $subB];
    };
});

test('customer cancels one restaurant sub-order and the other continues', function () {
    [$order, $subA, $subB] = ($this->makeTwoRestaurantOrder)();

    $cancelled = app(CancelRestaurantOrderAction::class)->handle($this->customer, $subA, 'No longer want this one');

    expect($cancelled->status)->toBe(RestaurantOrderStatus::Cancelled)
        ->and($cancelled->cancelled_by_id)->toBe($this->customer->id)
        ->and($subB->refresh()->status)->toBe(RestaurantOrderStatus::Preparing)
        ->and($order->refresh()->status)->toBe('payment-received');
});

test('restaurant staff cancels only their own sub-order', function () {
    [$order, $subA, $subB] = ($this->makeTwoRestaurantOrder)();

    $cancelled = app(CancelRestaurantOrderAction::class)->handle($this->staffA, $subA, 'Out of stock');

    expect($cancelled->status)->toBe(RestaurantOrderStatus::Cancelled)
        ->and($subB->refresh()->status)->toBe(RestaurantOrderStatus::Preparing);

    app(CancelRestaurantOrderAction::class)->handle($this->staffA, $subB, 'Not my restaurant');
})->throws(AuthorizationException::class);

test('admin cancels one sub-order and the sibling is untouched', function () {
    [$order, $subA, $subB] = ($this->makeTwoRestaurantOrder)();
    $admin = User::factory()->create(['type' => UserType::Admin]);

    $cancelled = app(CancelRestaurantOrderAction::class)->handle($admin, $subB, 'Customer support request');

    expect($cancelled->status)->toBe(RestaurantOrderStatus::Cancelled)
        ->and($cancelled->cancelled_by_id)->toBe($admin->id)
        ->and($subA->refresh()->status)->toBe(RestaurantOrderStatus::Preparing)
        ->and($order->refresh()->status)->toBe('payment-received');
});
