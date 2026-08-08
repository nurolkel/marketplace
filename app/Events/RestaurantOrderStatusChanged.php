<?php

namespace App\Events;

use App\Enums\RestaurantOrderStatus;
use App\Models\RestaurantOrder;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RestaurantOrderStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * The actor is null for guest checkouts and system-driven changes,
     * which have no authenticated user behind them.
     */
    public function __construct(
        public RestaurantOrder $restaurantOrder,
        public ?User $actor,
        public RestaurantOrderStatus $from,
        public RestaurantOrderStatus $to,
    ) {}
}
