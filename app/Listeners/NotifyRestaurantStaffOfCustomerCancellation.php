<?php

namespace App\Listeners;

use App\Enums\RestaurantOrderStatus;
use App\Events\RestaurantOrderStatusChanged;
use App\Models\User;
use App\Notifications\RestaurantOrderStatusChangedNotification;

class NotifyRestaurantStaffOfCustomerCancellation
{
    /**
     * Alert the restaurant's staff when the customer cancels their
     * sub-order, so prep work stops before ingredients are wasted. A
     * null actor means a guest cancelled their own order; staff or
     * admin cancellations stay silent.
     */
    public function handle(RestaurantOrderStatusChanged $event): void
    {
        if ($event->to !== RestaurantOrderStatus::Cancelled) {
            return;
        }

        $restaurantOrder = $event->restaurantOrder;
        $restaurant = $restaurantOrder->restaurant;

        if ($restaurant === null) {
            return;
        }

        if ($event->actor !== null && $event->actor->isNot($restaurantOrder->order->user)) {
            return;
        }

        $notification = new RestaurantOrderStatusChangedNotification(
            $restaurantOrder,
            $event->from,
            $event->to,
            $restaurantOrder->cancellation_reason,
        );

        $restaurant->staff->each(
            fn (User $member) => $member->notify($notification)
        );
    }
}
