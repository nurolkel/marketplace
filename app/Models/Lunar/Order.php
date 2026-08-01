<?php

namespace App\Models\Lunar;

use App\Models\RestaurantOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lunar\Models\Order as LunarOrder;

/**
 * The marketplace's single customer-facing order. Each order is split
 * into per-restaurant sub-orders (RestaurantOrder) at creation time.
 *
 * @property-read User|null $user
 * @property-read Collection<int, RestaurantOrder> $restaurantOrders
 */
class Order extends LunarOrder
{
    /**
     * Per-restaurant sub-orders this order was split into.
     *
     * @return HasMany<RestaurantOrder, $this>
     */
    public function restaurantOrders(): HasMany
    {
        return $this->hasMany(RestaurantOrder::class, 'order_id');
    }
}
