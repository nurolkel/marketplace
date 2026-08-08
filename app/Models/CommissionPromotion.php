<?php

namespace App\Models;

use Database\Factories\CommissionPromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A commission promotion offered to restaurants, e.g. "no commission
 * for your first 30 days" or "half commission for your first 100
 * orders". Rate in basis points (0 = commission-free); the promotion
 * ends at its duration or order cap, whichever comes first.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $rate
 * @property int|null $duration_days
 * @property int|null $max_orders
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Restaurant> $restaurants
 * @property-read RestaurantCommissionPromotion $pivot
 */
#[Fillable(['name', 'slug', 'rate', 'duration_days', 'max_orders', 'active'])]
class CommissionPromotion extends Model
{
    /** @use HasFactory<CommissionPromotionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Only promotions switched on.
     *
     * @param  Builder<CommissionPromotion>  $query
     * @return Builder<CommissionPromotion>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Restaurants this promotion has been assigned to.
     *
     * @return BelongsToMany<Restaurant, $this, RestaurantCommissionPromotion, 'pivot'>
     */
    public function restaurants(): BelongsToMany
    {
        return $this->belongsToMany(Restaurant::class, 'restaurant_commission_promotion')
            ->using(RestaurantCommissionPromotion::class)
            ->withPivot(['id', 'starts_at', 'ends_at', 'orders_used'])
            ->withTimestamps();
    }
}
