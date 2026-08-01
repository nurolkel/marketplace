<?php

use App\Actions\Orders\RefundRestaurantOrderAction;
use App\Enums\RestaurantOrderStatus;
use App\Enums\RestaurantRole;
use App\Events\RestaurantOrderStatusChanged;
use App\Exceptions\InvalidRestaurantOrderTransitionException;
use App\Exceptions\PaymentNotCapturedException;
use App\Exceptions\RefundExceedsRemainingAmountException;
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

    /**
     * A payment-received sub-order for the given total, whose parent
     * order has a matching captured offline transaction.
     */
    $this->makePaidSubOrder = function (int $total = 5000): RestaurantOrder {
        $order = Order::factory()->create();

        Transaction::factory()->create([
            'order_id' => $order->id,
            'type' => 'capture',
            'success' => true,
            'amount' => $total,
            'driver' => 'offline',
        ]);

        return RestaurantOrder::factory()->paymentReceived()->create([
            'order_id' => $order->id,
            'restaurant_id' => $this->restaurant->id,
            'sub_total' => $total,
            'total' => $total,
        ]);
    };
});

test('a full refund records an offline refund transaction and marks the sub-order refunded', function () {
    $subOrder = ($this->makePaidSubOrder)(5000);
    $capture = $subOrder->order->transactions()->where('type', 'capture')->firstOrFail();

    Event::fake([RestaurantOrderStatusChanged::class]);

    $subOrder = (new RefundRestaurantOrderAction)->handle($this->staff, $subOrder);

    expect($subOrder->status)->toBe(RestaurantOrderStatus::Refunded)
        ->and($subOrder->isTerminal())->toBeTrue();

    $refund = $subOrder->order->transactions()->where('type', 'refund')->sole();
    expect($refund->success)->toBeTruthy()
        ->and((int) $refund->getRawOriginal('amount'))->toBe(5000)
        ->and($refund->driver)->toBe('offline')
        ->and($refund->parent_transaction_id)->toBe($capture->id)
        ->and($refund->meta['restaurant_order_id'])->toBe($subOrder->id);

    Event::assertDispatched(
        RestaurantOrderStatusChanged::class,
        fn (RestaurantOrderStatusChanged $event): bool => $event->from === RestaurantOrderStatus::PaymentReceived
            && $event->to === RestaurantOrderStatus::Refunded
            && $event->actor->is($this->staff)
    );
});

test('a partial refund marks the sub-order partially refunded', function () {
    $subOrder = ($this->makePaidSubOrder)(5000);

    $subOrder = (new RefundRestaurantOrderAction)->handle($this->staff, $subOrder, 2000);

    expect($subOrder->status)->toBe(RestaurantOrderStatus::PartiallyRefunded);

    $refund = $subOrder->order->transactions()->where('type', 'refund')->sole();
    expect((int) $refund->getRawOriginal('amount'))->toBe(2000);
});

test('refunding more than the remaining refundable balance is rejected', function () {
    $subOrder = ($this->makePaidSubOrder)(5000);

    (new RefundRestaurantOrderAction)->handle($this->staff, $subOrder, 5001);
})->throws(RefundExceedsRemainingAmountException::class);

test('a second refund only has the remaining balance available', function () {
    $subOrder = ($this->makePaidSubOrder)(5000);
    $action = new RefundRestaurantOrderAction;

    $subOrder = $action->handle($this->staff, $subOrder, 2000);
    expect($subOrder->status)->toBe(RestaurantOrderStatus::PartiallyRefunded);

    $subOrder = $action->handle($this->staff, $subOrder);
    expect($subOrder->status)->toBe(RestaurantOrderStatus::Refunded);

    $refunds = $subOrder->order->transactions()->where('type', 'refund')->get();
    expect($refunds)->toHaveCount(2)
        ->and((int) $refunds->sum(fn (Transaction $transaction): int => (int) $transaction->getRawOriginal('amount')))->toBe(5000);
});

test('a fully refunded sub-order cannot be refunded again', function () {
    $subOrder = ($this->makePaidSubOrder)(5000);
    $action = new RefundRestaurantOrderAction;

    $subOrder = $action->handle($this->staff, $subOrder);

    $action->handle($this->staff, $subOrder, 1);
})->throws(InvalidRestaurantOrderTransitionException::class);

test('refunds require a captured payment on the parent order', function () {
    $order = Order::factory()->create();
    $subOrder = RestaurantOrder::factory()->paymentReceived()->create([
        'order_id' => $order->id,
        'restaurant_id' => $this->restaurant->id,
        'total' => 5000,
    ]);

    (new RefundRestaurantOrderAction)->handle($this->staff, $subOrder, 1000);
})->throws(PaymentNotCapturedException::class);

test('unpaid sub-orders are not refundable', function () {
    $order = Order::factory()->create();
    $subOrder = RestaurantOrder::factory()->create([
        'order_id' => $order->id,
        'restaurant_id' => $this->restaurant->id,
        'total' => 5000,
    ]);

    (new RefundRestaurantOrderAction)->handle($this->staff, $subOrder, 1000);
})->throws(InvalidRestaurantOrderTransitionException::class);

test('outsiders cannot refund sub-orders', function () {
    $outsider = User::factory()->create();
    $subOrder = ($this->makePaidSubOrder)(5000);

    (new RefundRestaurantOrderAction)->handle($outsider, $subOrder, 1000);
})->throws(AuthorizationException::class);
