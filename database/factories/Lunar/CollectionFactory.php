<?php

namespace Database\Factories\Lunar;

use App\Models\Lunar\Collection;
use App\Models\Restaurant;
use Lunar\Database\Factories\CollectionFactory as BaseCollectionFactory;

class CollectionFactory extends BaseCollectionFactory
{
    protected $model = Collection::class;

    public function definition(): array
    {
        return [
            ...parent::definition(),
            'restaurant_id' => Restaurant::factory(),
        ];
    }

    /**
     * Produce a marketplace-wide category with no restaurant attached.
     */
    public function marketplaceOwned(): static
    {
        return $this->state(fn (): array => [
            'restaurant_id' => null,
        ]);
    }
}
