<?php

namespace App\Models;

use App\Enums\RestaurantRole;
use App\Enums\RestaurantStatus;
use App\Models\Lunar\Product;
use Database\Factories\RestaurantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property RestaurantStatus $status
 * @property int|null $commission_tier_id
 * @property int|null $commission_rate
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, User> $owners
 * @property-read Collection<int, User> $staff
 * @property-read Collection<int, Product> $products
 * @property-read CommissionTier|null $commissionTier
 * @property-read Collection<int, Category> $categories
 * @property-read Collection<int, RestaurantOrder> $restaurantOrders
 * @property-read Collection<int, RestaurantPayout> $payouts
 * @property-read Collection<int, CommissionPromotion> $commissionPromotions
 * @property-read Collection<int, Review> $reviews
 */
#[Fillable(['name', 'slug', 'description', 'status'])]
class Restaurant extends Model
{
    /** @use HasFactory<RestaurantFactory> */
    use HasFactory, Searchable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RestaurantStatus::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Every user belonging to the restaurant, regardless of role.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Members holding the owner role.
     *
     * @return BelongsToMany<User, $this>
     */
    public function owners(): BelongsToMany
    {
        return $this->members()->wherePivot('role', RestaurantRole::Owner->value);
    }

    /**
     * Everyone who works at the restaurant. Membership of any role
     * counts as staff for day-to-day operations like pricing and sales.
     *
     * @return BelongsToMany<User, $this>
     */
    public function staff(): BelongsToMany
    {
        return $this->members();
    }

    /**
     * Products this restaurant sells on the marketplace.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Categories from the platform taxonomy this restaurant is tagged
     * with (e.g. a restaurant can be both "Italian" and "Pizzeria").
     *
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withTimestamps();
    }

    /**
     * The fields matched when customers search the marketplace.
     * Category names are included so searching a cuisine
     * ("pizzeria") finds categorized restaurants.
     *
     * @return array<string, string>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => (string) $this->description,
            'categories' => $this->categories->pluck('name')->implode(' '),
        ];
    }

    /**
     * Only active restaurants appear in storefront search results.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->status === RestaurantStatus::Active;
    }

    /**
     * Sub-orders this restaurant is responsible for fulfilling.
     *
     * @return HasMany<RestaurantOrder, $this>
     */
    public function restaurantOrders(): HasMany
    {
        return $this->hasMany(RestaurantOrder::class);
    }

    /**
     * Payout ledger entries owed to this restaurant.
     *
     * @return HasMany<RestaurantPayout, $this>
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(RestaurantPayout::class);
    }

    /**
     * Customer reviews of this restaurant.
     *
     * @return MorphMany<Review, $this>
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    /**
     * The restaurant's average review rating, or null when it has no
     * reviews yet.
     */
    public function averageRating(): ?float
    {
        $average = $this->reviews()->avg('rating');

        return $average === null ? null : round((float) $average, 1);
    }

    /**
     * The restaurant's rung on the commission scale. Null means the
     * platform's default tier applies.
     *
     * @return BelongsTo<CommissionTier, $this>
     */
    public function commissionTier(): BelongsTo
    {
        return $this->belongsTo(CommissionTier::class);
    }

    /**
     * Commission promotions assigned to this restaurant, with their
     * schedules and fulfilled-order counters on the pivot.
     *
     * @return BelongsToMany<CommissionPromotion, $this, RestaurantCommissionPromotion, 'pivot'>
     */
    public function commissionPromotions(): BelongsToMany
    {
        return $this->belongsToMany(CommissionPromotion::class, 'restaurant_commission_promotion')
            ->using(RestaurantCommissionPromotion::class)
            ->withPivot(['id', 'starts_at', 'ends_at', 'orders_used'])
            ->withTimestamps();
    }

    /**
     * The assigned promotion currently in effect, if any: switched
     * on, inside its schedule, and under its order cap. Assignments
     * are replaced rather than stacked, so at most one applies.
     */
    public function activeCommissionPromotion(): ?RestaurantCommissionPromotion
    {
        /** @var CommissionPromotion|null $promotion */
        $promotion = $this->commissionPromotions()->first();

        if ($promotion === null) {
            return null;
        }

        /** @var RestaurantCommissionPromotion $assignment */
        $assignment = $promotion->pivot;

        return $assignment->isInEffect() ? $assignment : null;
    }

    /**
     * The commission charged on this restaurant's fulfilled
     * sub-orders, in basis points: an in-effect promotion first,
     * then a custom override, otherwise the rate of the restaurant's
     * tier (the platform standard by default).
     */
    public function effectiveCommissionRate(): int
    {
        $promotion = $this->activeCommissionPromotion();

        if ($promotion instanceof RestaurantCommissionPromotion) {
            return $promotion->promotion->rate;
        }

        if ($this->commission_rate !== null) {
            return $this->commission_rate;
        }

        /** @var CommissionTier|null $tier */
        $tier = $this->commissionTier()->first();

        if ($tier instanceof CommissionTier) {
            return $tier->rate;
        }

        /** @var CommissionTier|null $defaultTier */
        $defaultTier = CommissionTier::query()->where('is_default', true)->first();

        return $defaultTier instanceof CommissionTier
            ? $defaultTier->rate
            : CommissionTier::FALLBACK_RATE;
    }
}
