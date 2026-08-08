<?php

namespace App\Listeners;

use App\Enums\RestaurantOrderStatus;
use App\Events\RestaurantOrderStatusChanged;
use App\Models\User;
use App\Notifications\RestaurantOrderStatusChangedNotification;
use Illuminate\Support\Facades\Notification;
use Lunar\Models\OrderAddress;

class NotifyCustomerOfRestaurantOrderStatusChange
{
    /**
     * Tell the customer about every meaningful step of their order:
     * payment, acceptance, preparation, pauses (with the restaurant's
     * reason), resumes, dispatch, completion, cancellations, and
     * refunds. Guests get on-demand mail to the order's contact email.
     */
    public function handle(RestaurantOrderStatusChanged $event): void
    {
        if (! $this->shouldNotify($event)) {
            return;
        }

        $notification = new RestaurantOrderStatusChangedNotification(
            $event->restaurantOrder,
            $event->from,
            $event->to,
            $this->reason($event),
        );

        $customer = $event->restaurantOrder->order->user;

        if ($customer instanceof User) {
            $customer->notify($notification);

            return;
        }

        $order = $event->restaurantOrder->order;
        $billing = $order->billingAddress;
        $shipping = $order->shippingAddress;

        $email = ($billing instanceof OrderAddress ? $billing->contact_email : null)
            ?? ($shipping instanceof OrderAddress ? $shipping->contact_email : null);

        if ($email === null) {
            return;
        }

        Notification::route('mail', $email)->notify($notification);
    }

    /**
     * A resume is any transition out of OnHold.
     */
    private function shouldNotify(RestaurantOrderStatusChanged $event): bool
    {
        return $event->from === RestaurantOrderStatus::OnHold
            || in_array($event->to, [
                RestaurantOrderStatus::PaymentReceived,
                RestaurantOrderStatus::Accepted,
                RestaurantOrderStatus::Preparing,
                RestaurantOrderStatus::OnHold,
                RestaurantOrderStatus::Dispatched,
                RestaurantOrderStatus::Completed,
                RestaurantOrderStatus::Cancelled,
                RestaurantOrderStatus::PartiallyRefunded,
                RestaurantOrderStatus::Refunded,
            ], true);
    }

    private function reason(RestaurantOrderStatusChanged $event): ?string
    {
        return match ($event->to) {
            RestaurantOrderStatus::OnHold => $event->restaurantOrder->pause_reason,
            RestaurantOrderStatus::Cancelled => $event->restaurantOrder->cancellation_reason,
            default => null,
        };
    }
}
