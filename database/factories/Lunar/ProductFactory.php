<?php

namespace Database\Factories\Lunar;

use App\Models\Lunar\Product;
use App\Models\Restaurant;
use Lunar\Database\Factories\ProductFactory as BaseProductFactory;

class ProductFactory extends BaseProductFactory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            ...parent::definition(),
            'restaurant_id' => Restaurant::factory(),
        ];
    }

    /**
     * Produce a marketplace-owned product with no restaurant attached.
     */
    public function marketplaceOwned(): static
    {
        return $this->state(fn (): array => [
            'restaurant_id' => null,
        ]);
    }
}
