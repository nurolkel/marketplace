<?php

use App\Enums\RestaurantOrderStatus;
use App\Enums\RestaurantRole;
use App\Enums\UserType;
use App\Models\Lunar\Order;
use App\Models\Restaurant;
use App\Models\RestaurantOrder;
use App\Models\User;
use Lunar\Models\Currency;
use Lunar\Models\Language;

beforeEach(function () {
    Language::factory()->create(['code' => 'en', 'default' => true]);
    Currency::factory()->create(['default' => true]);

    $this->restaurant = Restaurant::factory()->create();

    $this->owner = User::factory()->create();
    $this->manager = User::factory()->create();
    $this->staff = User::factory()->create();
    $this->restaurant->members()->attach($this->owner, ['role' => RestaurantRole::Owner->value]);
    $this->restaurant->members()->attach($this->manager, ['role' => RestaurantRole::Manager->value]);
    $this->restaurant->members()->attach($this->staff, ['role' => RestaurantRole::Staff->value]);

    $this->customer = User::factory()->create();
    $this->outsider = User::factory()->create();
    $this->admin = User::factory()->create(['type' => UserType::Admin]);

    $this->makeSubOrder = function (?RestaurantOrderStatus $status = null): RestaurantOrder {
        $order = Order::factory()->create(['user_id' => $this->customer->id]);

        return RestaurantOrder::factory()->create([
            'order_id' => $order->id,
            'restaurant_id' => $this->restaurant->id,
            'status' => $status ?? RestaurantOrderStatus::Pending,
            'paused_from_status' => $status === RestaurantOrderStatus::OnHold ? 'preparing' : null,
        ]);
    };
});

test('view is open to the customer and restaurant staff only', function (string $user, bool $expected) {
    expect($this->{$user}->can('view', ($this->makeSubOrder)()))->toBe($expected);
})->with([
    'customer' => ['customer', true],
    'owner' => ['owner', true],
    'manager' => ['manager', true],
    'staff' => ['staff', true],
    'outsider' => ['outsider', false],
    'admin' => ['admin', true],
]);

test('manage abilities are open to every staff role and admins only', function (string $ability, string $user, bool $expected) {
    expect($this->{$user}->can($ability, ($this->makeSubOrder)(RestaurantOrderStatus::Preparing)))->toBe($expected);
})->with([
    'transition staff' => ['transition', 'staff', true],
    'transition manager' => ['transition', 'manager', true],
    'transition owner' => ['transition', 'owner', true],
    'transition customer' => ['transition', 'customer', false],
    'transition outsider' => ['transition', 'outsider', false],
    'transition admin' => ['transition', 'admin', true],
    'pause staff' => ['pause', 'staff', true],
    'pause customer' => ['pause', 'customer', false],
    'resume staff' => ['resume', 'staff', true],
    'resume outsider' => ['resume', 'outsider', false],
    'refund staff' => ['refund', 'staff', true],
    'refund owner' => ['refund', 'owner', true],
    'refund customer' => ['refund', 'customer', false],
    'refund outsider' => ['refund', 'outsider', false],
    'refund admin' => ['refund', 'admin', true],
]);

test('the customer cancel window stays open until dispatch', function (RestaurantOrderStatus $status, bool $expected) {
    expect($this->customer->can('cancel', ($this->makeSubOrder)($status)))->toBe($expected);
})->with([
    'pending' => [RestaurantOrderStatus::Pending, true],
    'payment received' => [RestaurantOrderStatus::PaymentReceived, true],
    'accepted' => [RestaurantOrderStatus::Accepted, true],
    'preparing' => [RestaurantOrderStatus::Preparing, true],
    'on hold' => [RestaurantOrderStatus::OnHold, true],
    'dispatched' => [RestaurantOrderStatus::Dispatched, false],
    'completed' => [RestaurantOrderStatus::Completed, false],
]);

test('staff may attempt cancellation at any lifecycle point; the action enforces the map', function (RestaurantOrderStatus $status) {
    expect($this->staff->can('cancel', ($this->makeSubOrder)($status)))->toBeTrue()
        ->and($this->outsider->can('cancel', ($this->makeSubOrder)($status)))->toBeFalse();
})->with([
    'pending' => [RestaurantOrderStatus::Pending],
    'preparing' => [RestaurantOrderStatus::Preparing],
    'dispatched' => [RestaurantOrderStatus::Dispatched],
]);

test('marketplace sub-orders are admin-managed and customer-viewable', function () {
    $order = Order::factory()->create(['user_id' => $this->customer->id]);
    $subOrder = RestaurantOrder::factory()->marketplaceOwned()->create(['order_id' => $order->id]);

    expect($this->customer->can('view', $subOrder))->toBeTrue()
        ->and($this->staff->can('view', $subOrder))->toBeFalse()
        ->and($this->staff->can('transition', $subOrder))->toBeFalse()
        ->and($this->staff->can('refund', $subOrder))->toBeFalse()
        ->and($this->admin->can('view', $subOrder))->toBeTrue()
        ->and($this->admin->can('transition', $subOrder))->toBeTrue()
        ->and($this->admin->can('refund', $subOrder))->toBeTrue();
});
