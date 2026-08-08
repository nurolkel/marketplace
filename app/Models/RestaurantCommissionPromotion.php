<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * A commission promotion assigned to a restaurant, with its own
 * schedule (starts_at/ends_at) and fulfilled-order counter.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $commission_promotion_id
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property int $orders_used
 * @property-read CommissionPromotion $promotion
 * @property-read Restaurant $restaurant
 */
class RestaurantCommissionPromotion extends Pivot
{
    protected $table = 'restaurant_commission_promotion';

    public $incrementing = true;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CommissionPromotion, $this>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(CommissionPromotion::class, 'commission_promotion_id');
    }

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Whether the promotion currently applies: switched on, inside
     * its schedule, and under its order cap (when either is set).
     */
    public function isInEffect(): bool
    {
        if (! $this->promotion->active) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return false;
        }

        $maxOrders = $this->promotion->max_orders;

        return $maxOrders === null || $this->orders_used < $maxOrders;
    }
}
