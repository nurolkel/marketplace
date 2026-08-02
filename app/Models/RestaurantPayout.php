<?php

namespace App\Models;

use Database\Factories\RestaurantPayoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A restaurant's payable balance for one fulfilled sub-order. Records
 * are created when the sub-order completes and stay `pending` (in
 * escrow) until 30 days after fulfilment, when the `eligible` scope
 * surfaces them for payout. Money movement itself is external.
 *
 * @property int $id
 * @property int $restaurant_order_id
 * @property int $restaurant_id
 * @property int $gross_amount
 * @property int $commission_amount
 * @property int $net_amount
 * @property string $status
 * @property Carbon|null $eligible_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RestaurantOrder $restaurantOrder
 * @property-read Restaurant $restaurant
 */
#[Fillable(['restaurant_order_id', 'restaurant_id', 'gross_amount', 'commission_amount', 'net_amount', 'status', 'eligible_at', 'paid_at'])]
class RestaurantPayout extends Model
{
    /** @use HasFactory<RestaurantPayoutFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'eligible_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * The sub-order this payout settles.
     *
     * @return BelongsTo<RestaurantOrder, $this>
     */
    public function restaurantOrder(): BelongsTo
    {
        return $this->belongsTo(RestaurantOrder::class);
    }

    /**
     * The restaurant owed the payout.
     *
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Payouts past their 30-day escrow window that can be paid out.
     *
     * @param  Builder<RestaurantPayout>  $query
     * @return Builder<RestaurantPayout>
     */
    public function scopeEligible(Builder $query): Builder
    {
        return $query->where('status', 'pending')->where('eligible_at', '<=', now());
    }

    /**
     * Mark the payout as paid.
     */
    public function markPaid(): void
    {
        $this->update(['status' => 'paid', 'paid_at' => now()]);
    }
}
