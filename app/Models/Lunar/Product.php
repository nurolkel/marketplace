<?php

namespace App\Models\Lunar;

use App\Models\Restaurant;
use Database\Factories\Lunar\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Models\Product as LunarProduct;

/**
 * @property int|null $restaurant_id
 * @property Restaurant|null $restaurant
 */
class Product extends LunarProduct
{
    public function __construct(array $attributes = [])
    {
        $this->mergeFillable(['restaurant_id']);

        parent::__construct($attributes);
    }

    /**
     * Return a new factory instance for the model.
     */
    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    /**
     * The restaurant that owns this product. Null means the item is
     * owned by the marketplace itself.
     *
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Scope the query to products owned by the given restaurant.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeForRestaurant(Builder $query, Restaurant $restaurant): Builder
    {
        return $query->where($this->qualifyColumn('restaurant_id'), $restaurant->id);
    }
}
