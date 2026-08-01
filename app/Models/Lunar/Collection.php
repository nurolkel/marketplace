<?php

namespace App\Models\Lunar;

use App\Models\Restaurant;
use Database\Factories\Lunar\CollectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\CollectionGroup;

/**
 * A product category. Lunar calls categories "collections".
 *
 * @property int $id
 * @property int $collection_group_id
 * @property string $type
 * @property \Illuminate\Support\Collection<string, mixed>|null $attribute_data
 * @property string $sort
 * @property int|null $restaurant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Restaurant|null $restaurant
 * @property-read CollectionGroup $group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Product> $products
 */
class Collection extends LunarCollection
{
    /**
     * Return a new factory instance for the model.
     */
    protected static function newFactory(): CollectionFactory
    {
        return CollectionFactory::new();
    }

    /**
     * The restaurant that owns this category. Null means it is a
     * marketplace-wide category managed by the platform.
     *
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Scope the query to categories owned by the given restaurant.
     *
     * @param  Builder<Collection>  $query
     * @return Builder<Collection>
     */
    public function scopeForRestaurant(Builder $query, Restaurant $restaurant): Builder
    {
        return $query->where($this->qualifyColumn('restaurant_id'), $restaurant->id);
    }

    /**
     * The category's display name from its attribute data.
     */
    public function displayName(): ?string
    {
        return $this->translateAttribute('name');
    }

    /**
     * Deterministic search payload, independent of Lunar's attribute
     * manifest metadata.
     *
     * @return array<string, string>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->id,
            'name' => (string) $this->displayName(),
        ];
    }
}
