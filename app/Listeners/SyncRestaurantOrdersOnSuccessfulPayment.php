<?php

namespace App\Listeners;

use App\Enums\RestaurantOrderStatus;
use App\Events\RestaurantOrderStatusChanged;
use App\Models\Lunar\Order;
use App\Models\RestaurantOrder;
use Lunar\Events\PaymentAttemptEvent;

class SyncRestaurantOrdersOnSuccessfulPayment
{
    /**
     * Move Pending sub-orders to PaymentReceived when a payment
     * authorizes against their parent order, so restaurants only see
     * paid work. Every payment driver (Stripe, offline) dispatches
     * this event, so the sync works for all of them.
     */
    public function handle(PaymentAttemptEvent $event): void
    {
        $authorize = $event->paymentAuthorize;

        if (! $authorize->success || $authorize->orderId === null) {
            return;
        }

        $order = Order::find($authorize->orderId);

        if (! $order instanceof Order) {
            return;
        }

        $order->restaurantOrders()
            ->where('status', RestaurantOrderStatus::Pending->value)
            ->get()
            ->each(function (RestaurantOrder $subOrder) use ($order): void {
                $from = $subOrder->status;

                $subOrder->update([
                    'status' => RestaurantOrderStatus::PaymentReceived,
                    'placed_at' => $subOrder->placed_at ?? now(),
                ]);

                // Guest orders have no user; a null actor still notifies
                // the guest by on-demand mail via the customer listener.
                RestaurantOrderStatusChanged::dispatch(
                    $subOrder->refresh(), $order->user, $from, RestaurantOrderStatus::PaymentReceived,
                );
            });
    }
}
