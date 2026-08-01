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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property RestaurantStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, User> $owners
 * @property-read Collection<int, User> $staff
 * @property-read Collection<int, Product> $products
 * @property-read Collection<int, RestaurantOrder> $restaurantOrders
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
     * The fields matched when customers search the marketplace.
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
}
