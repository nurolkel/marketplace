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

    public function __construct(
        public RestaurantOrder $restaurantOrder,
        public User $actor,
        public RestaurantOrderStatus $from,
        public RestaurantOrderStatus $to,
    ) {}
}
