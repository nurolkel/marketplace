<?php

namespace App\Listeners;

use App\Enums\RestaurantOrderStatus;
use App\Events\RestaurantOrderStatusChanged;
use App\Models\User;
use App\Notifications\RestaurantOrderStatusChangedNotification;

class NotifyCustomerOfRestaurantOrderStatusChange
{
    /**
     * Tell the customer about the changes they care about: pauses
     * (with the restaurant's reason), resumes, cancellations,
     * refunds, and dispatch.
     */
    public function handle(RestaurantOrderStatusChanged $event): void
    {
        if (! $this->shouldNotify($event)) {
            return;
        }

        $customer = $event->restaurantOrder->order->user;

        if (! $customer instanceof User) {
            return;
        }

        $customer->notify(new RestaurantOrderStatusChangedNotification(
            $event->restaurantOrder,
            $event->from,
            $event->to,
            $this->reason($event),
        ));
    }

    /**
     * A resume is any transition out of OnHold.
     */
    private function shouldNotify(RestaurantOrderStatusChanged $event): bool
    {
        return $event->from === RestaurantOrderStatus::OnHold
            || in_array($event->to, [
                RestaurantOrderStatus::OnHold,
                RestaurantOrderStatus::Dispatched,
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
