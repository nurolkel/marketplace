<?php

namespace App\Listeners;

use App\Enums\RestaurantOrderStatus;
use App\Events\RestaurantOrderStatusChanged;
use App\Models\RestaurantPayout;

class CreateRestaurantPayoutRecord
{
    /**
     * Record the restaurant's payable balance when a sub-order is
     * fulfilled. The record sits in escrow (pending) until 30 days
     * after completion. Marketplace-owned sub-orders have no
     * restaurant to pay and are skipped.
     */
    public function handle(RestaurantOrderStatusChanged $event): void
    {
        if ($event->to !== RestaurantOrderStatus::Completed) {
            return;
        }

        $subOrder = $event->restaurantOrder;
        $restaurant = $subOrder->restaurant;

        if ($restaurant === null) {
            return;
        }

        $commission = (int) round($subOrder->total * $restaurant->effectiveCommissionRate() / 10000);

        $payout = RestaurantPayout::firstOrCreate(
            ['restaurant_order_id' => $subOrder->id],
            [
                'restaurant_id' => $restaurant->id,
                'gross_amount' => $subOrder->total,
                'commission_amount' => $commission,
                'net_amount' => $subOrder->total - $commission,
                'status' => 'pending',
                'eligible_at' => ($subOrder->completed_at ?? now())->addDays(30),
            ],
        );

        // Count the fulfilled sub-order against the promotion's order
        // cap. Tied to payout creation so a repeated Completed event
        // cannot inflate the counter.
        if ($payout->wasRecentlyCreated) {
            $restaurant->activeCommissionPromotion()?->increment('orders_used');
        }
    }
}
