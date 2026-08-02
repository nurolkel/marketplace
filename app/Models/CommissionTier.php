<?php

namespace App\Models;

use Database\Factories\CommissionTierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A rung on the platform's sliding commission scale (rate in basis
 * points, 1500 = 15%). Restaurants sit on a tier; the default tier is
 * the platform standard. Tiers are data, editable at any time.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $rate
 * @property bool $is_default
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Restaurant> $restaurants
 */
#[Fillable(['name', 'slug', 'rate', 'is_default', 'sort_order'])]
class CommissionTier extends Model
{
    /** The platform standard rate in basis points when the tiers table is empty (e.g. before seeding). */
    public const FALLBACK_RATE = 1500;

    /** @use HasFactory<CommissionTierFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The platform's standard tier, applied to restaurants with no
     * tier assigned.
     */
    public static function default(): self
    {
        /** @var self */
        return static::query()->where('is_default', true)->firstOrFail();
    }

    /**
     * Restaurants assigned to this tier.
     *
     * @return HasMany<Restaurant, $this>
     */
    public function restaurants(): HasMany
    {
        return $this->hasMany(Restaurant::class);
    }
}
