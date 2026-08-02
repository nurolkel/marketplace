<?php

use App\Actions\Orders\CancelRestaurantOrderAction;
use App\Enums\RestaurantOrderStatus;
use App\Enums\RestaurantRole;
use App\Enums\UserType;
use App\Events\RestaurantOrderStatusChanged;
use App\Exceptions\InvalidRestaurantOrderTransitionException;
use App\Models\Lunar\Order;
use App\Models\Restaurant;
use App\Models\RestaurantOrder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Transaction;

beforeEach(function () {
    Language::factory()->create(['code' => 'en', 'default' => true]);
    Currency::factory()->create(['default' => true]);

    $this->restaurant = Restaurant::factory()->create();
    $this->staff = User::factory()->create();
    $this->restaurant->members()->attach($this->staff, ['role' => RestaurantRole::Staff->value]);
    $this->customer = User::factory()->create();

    $this->makeSubOrder = function (RestaurantOrderStatus $status): RestaurantOrder {
        $order = Order::factory()->create(['user_id' => $this->customer->id]);

        return RestaurantOrder::factory()->create([
            'order_id' => $order->id,
            'restaurant_id' => $this->restaurant->id,
            'status' => $status,
        ]);
    };
});

test('customers can cancel within their window', function (RestaurantOrderStatus $status) {
    $subOrder = ($this->makeSubOrder)($status);

    Event::fake([RestaurantOrderStatusChanged::class]);

    $subOrder = app(CancelRestaurantOrderAction::class)->handle($this->customer, $subOrder, 'Changed my mind');

    expect($subOrder->status)->toBe(RestaurantOrderStatus::Cancelled)
        ->and($subOrder->cancelled_at)->not->toBeNull()
        ->and($subOrder->cancelled_by_id)->toBe($this->customer->id)
        ->and($subOrder->cancellation_reason)->toBe('Changed my mind')
        ->and($subOrder->cancelledBy->is($this->customer))->toBeTrue();

    Event::assertDispatched(
        RestaurantOrderStatusChanged::class,
        fn (RestaurantOrderStatusChanged $event): bool => $event->to === RestaurantOrderStatus::Cancelled
            && $event->actor->is($this->customer)
    );
})->with([
    'pending' => [RestaurantOrderStatus::Pending],
    'payment received' => [RestaurantOrderStatus::PaymentReceived],
    'accepted' => [RestaurantOrderStatus::Accepted],
    'preparing' => [RestaurantOrderStatus::Preparing],
    'on hold' => [RestaurantOrderStatus::OnHold],
]);

test('customers cannot cancel once the order is sent out or terminal', function (RestaurantOrderStatus $status) {
    $subOrder = ($this->makeSubOrder)($status);

    app(CancelRestaurantOrderAction::class)->handle($this->customer, $subOrder, 'Too late');
})->with([
    'dispatched' => [RestaurantOrderStatus::Dispatched],
    'completed' => [RestaurantOrderStatus::Completed],
    'already cancelled' => [RestaurantOrderStatus::Cancelled],
    'refunded' => [RestaurantOrderStatus::Refunded],
])->throws(AuthorizationException::class);

test('staff can cancel any non-dispatched, non-terminal sub-order', function () {
    $subOrder = ($this->makeSubOrder)(RestaurantOrderStatus::Preparing);

    $subOrder = app(CancelRestaurantOrderAction::class)->handle($this->staff, $subOrder, 'Ingredient shortage');

    expect($subOrder->status)->toBe(RestaurantOrderStatus::Cancelled)
        ->and($subOrder->cancelled_by_id)->toBe($this->staff->id);
});

test('staff cannot cancel dispatched or terminal sub-orders', function (RestaurantOrderStatus $status) {
    $subOrder = ($this->makeSubOrder)($status);

    app(CancelRestaurantOrderAction::class)->handle($this->staff, $subOrder, 'Too late');
})->with([
    'dispatched' => [RestaurantOrderStatus::Dispatched],
    'completed' => [RestaurantOrderStatus::Completed],
    'already cancelled' => [RestaurantOrderStatus::Cancelled],
])->throws(InvalidRestaurantOrderTransitionException::class);

test('cancelling with refund issues a full refund first and still ends cancelled', function () {
    $subOrder = ($this->makeSubOrder)(RestaurantOrderStatus::PaymentReceived);
    $subOrder->forceFill(['total' => 5000])->save();

    Transaction::factory()->create([
        'order_id' => $subOrder->order_id,
        'type' => 'capture',
        'success' => true,
        'amount' => 5000,
        'driver' => 'offline',
    ]);

    $subOrder = app(CancelRestaurantOrderAction::class)->handle($this->staff, $subOrder, 'Cannot fulfil', refund: true);

    expect($subOrder->status)->toBe(RestaurantOrderStatus::Cancelled);

    $refund = $subOrder->order->transactions()->where('type', 'refund')->first();
    expect($refund)->not->toBeNull()
        ->and((int) $refund->getRawOriginal('amount'))->toBe(5000)
        ->and($refund->meta['restaurant_order_id'])->toBe($subOrder->id);
});

test('cancelling with refund skips the refund when nothing was captured', function () {
    $subOrder = ($this->makeSubOrder)(RestaurantOrderStatus::Pending);

    $subOrder = app(CancelRestaurantOrderAction::class)->handle($this->staff, $subOrder, 'Duplicate order', refund: true);

    expect($subOrder->status)->toBe(RestaurantOrderStatus::Cancelled)
        ->and($subOrder->order->transactions()->where('type', 'refund')->exists())->toBeFalse();
});

test('customers cannot trigger refunds through cancellation', function () {
    $subOrder = ($this->makeSubOrder)(RestaurantOrderStatus::PaymentReceived);

    app(CancelRestaurantOrderAction::class)->handle($this->customer, $subOrder, 'Want my money back', refund: true);
})->throws(AuthorizationException::class);

test('marketplace sub-orders can be cancelled by admins but not by restaurant staff', function () {
    $order = Order::factory()->create(['user_id' => $this->customer->id]);
    $admin = User::factory()->create(['type' => UserType::Admin]);

    $staffTarget = RestaurantOrder::factory()->marketplaceOwned()->create([
        'order_id' => $order->id,
        'status' => RestaurantOrderStatus::Preparing,
    ]);
    $adminTarget = RestaurantOrder::factory()->marketplaceOwned()->create([
        'order_id' => $order->id,
        'status' => RestaurantOrderStatus::Preparing,
    ]);

    $cancelled = app(CancelRestaurantOrderAction::class)->handle($admin, $adminTarget, 'Marketplace recall');
    expect($cancelled->status)->toBe(RestaurantOrderStatus::Cancelled);

    app(CancelRestaurantOrderAction::class)->handle($this->staff, $staffTarget, 'Not their order');
})->throws(AuthorizationException::class);
