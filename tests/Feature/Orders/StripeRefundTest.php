<?php

use App\Actions\Orders\CancelRestaurantOrderAction;
use App\Actions\Orders\RefundRestaurantOrderAction;
use App\Enums\RestaurantOrderStatus;
use App\Enums\RestaurantRole;
use App\Models\Lunar\Order;
use App\Models\Restaurant;
use App\Models\RestaurantOrder;
use App\Models\User;
use Lunar\Facades\Payments;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Transaction;
use Tests\Support\FakeStripePaymentDriver;

beforeEach(function () {
    Language::factory()->create(['code' => 'en', 'default' => true]);
    Currency::factory()->create(['default' => true]);

    $this->restaurant = Restaurant::factory()->create();
    $this->staff = User::factory()->create();
    $this->restaurant->members()->attach($this->staff, ['role' => RestaurantRole::Staff->value]);

    $fakeDriver = new FakeStripePaymentDriver;
    $this->fakeDriver = $fakeDriver;
    Payments::extend('stripe', fn (): FakeStripePaymentDriver => $fakeDriver);

    /**
     * A payment-received sub-order whose parent order was captured by
     * the Stripe driver (reference = Stripe charge id).
     */
    $this->makeStripePaidSubOrder = function (int $total = 5000): RestaurantOrder {
        $order = Order::factory()->create();

        Transaction::factory()->create([
            'order_id' => $order->id,
            'type' => 'capture',
            'success' => true,
            'amount' => $total,
            'driver' => 'stripe',
            'reference' => 'ch_test_123',
            'card_type' => 'visa',
            'last_four' => '4242',
        ]);

        return RestaurantOrder::factory()->paymentReceived()->create([
            'order_id' => $order->id,
            'restaurant_id' => $this->restaurant->id,
            'sub_total' => $total,
            'total' => $total,
        ]);
    };
});

test('a stripe refund claims the gateway-recorded transaction instead of duplicating it', function () {
    $subOrder = ($this->makeStripePaidSubOrder)(5000);
    $capture = $subOrder->order->transactions()->where('type', 'capture')->firstOrFail();

    $subOrder = (new RefundRestaurantOrderAction)->handle($this->staff, $subOrder);

    expect($subOrder->status)->toBe(RestaurantOrderStatus::Refunded);

    $refund = $subOrder->order->transactions()->where('type', 'refund')->sole();
    expect($refund->driver)->toBe('stripe')
        ->and((int) $refund->getRawOriginal('amount'))->toBe(5000)
        ->and($refund->parent_transaction_id)->toBe($capture->id)
        ->and($refund->meta['restaurant_order_id'])->toBe($subOrder->id);
});

test('sequential stripe partial refunds each get claimed for the sub-order', function () {
    $subOrder = ($this->makeStripePaidSubOrder)(5000);
    $action = new RefundRestaurantOrderAction;

    $subOrder = $action->handle($this->staff, $subOrder, 2000);
    expect($subOrder->status)->toBe(RestaurantOrderStatus::PartiallyRefunded);

    $subOrder = $action->handle($this->staff, $subOrder);
    expect($subOrder->status)->toBe(RestaurantOrderStatus::Refunded);

    $refunds = $subOrder->order->transactions()->where('type', 'refund')->get();
    expect($refunds)->toHaveCount(2)
        ->and($refunds->every(fn (Transaction $refund): bool => $refund->meta['restaurant_order_id'] === $subOrder->id))->toBeTrue()
        ->and((int) $refunds->sum(fn (Transaction $refund): int => (int) $refund->getRawOriginal('amount')))->toBe(5000);
});

test('cancelling a stripe-paid sub-order with refund issues a full refund first', function () {
    $subOrder = ($this->makeStripePaidSubOrder)(5000);

    $subOrder = app(CancelRestaurantOrderAction::class)->handle($this->staff, $subOrder, 'Cannot fulfil', refund: true);

    expect($subOrder->status)->toBe(RestaurantOrderStatus::Cancelled);

    $refund = $subOrder->order->transactions()->where('type', 'refund')->sole();
    expect($refund->driver)->toBe('stripe')
        ->and((int) $refund->getRawOriginal('amount'))->toBe(5000)
        ->and($refund->meta['restaurant_order_id'])->toBe($subOrder->id);
});
