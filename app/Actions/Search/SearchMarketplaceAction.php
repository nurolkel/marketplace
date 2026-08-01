<?php

namespace App\Actions\Search;

use App\Models\Lunar\Collection;
use App\Models\Lunar\Product;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class SearchMarketplaceAction
{
    /**
     * Search the marketplace across restaurants, categories, and food
     * items with a single query string.
     *
     * @return array{restaurants: EloquentCollection<int, Restaurant>, categories: EloquentCollection<int, Collection>, products: EloquentCollection<int, Product>}
     */
    public function handle(string $query): array
    {
        return [
            'restaurants' => Restaurant::search($query)->get(),
            'categories' => Collection::search($query)->get(),
            'products' => Product::search($query)->get(),
        ];
    }
}
