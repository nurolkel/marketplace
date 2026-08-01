<?php

namespace App\Actions\Search;

use App\Models\Lunar\Product;
use App\Models\Restaurant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Lunar\Models\Price;
use Lunar\Models\ProductVariant;

class SearchRestaurantProductsAction
{
    /**
     * Sorts customers may request. Anything else falls back to relevance
     * so raw input never reaches an orderBy clause.
     */
    private const array SORTS = ['relevance', 'name', 'price_asc', 'price_desc', 'newest'];

    /**
     * Search the menu of a single restaurant by text, optionally filtered
     * to a Lunar collection (menu section), with a whitelisted sort. Only
     * published products are listed. A blank query browses the whole menu;
     * relevance without a query means newest first. Price sorts use each
     * product's minimum variant price; name sorts read the name from the
     * product's attribute data (Text field type, "$.name.value").
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function handle(Restaurant $restaurant, ?string $query, ?int $collectionId = null, string $sort = 'relevance', int $perPage = 15): LengthAwarePaginator
    {
        $query = trim((string) $query);
        $sort = in_array($sort, self::SORTS, true) ? $sort : 'relevance';

        $products = Product::query()
            ->forRestaurant($restaurant)
            ->status('published');

        $rankedIds = collect();
        if ($query !== '') {
            $rankedIds = $this->matchingIds($restaurant, $query);
            $products->whereIn((new Product)->qualifyColumn('id'), $rankedIds);
        }

        if ($collectionId !== null) {
            $products->whereHas('collections', fn (Builder $collections): Builder => $collections->whereKey($collectionId));
        }

        $this->applySort($products, $sort, $rankedIds);

        return $products->paginate($perPage);
    }

    /**
     * Ranked ids from the Scout engine for the given query, scoped to the
     * restaurant through the indexed restaurant_id attribute.
     *
     * @return Collection<int, int>
     */
    private function matchingIds(Restaurant $restaurant, string $query): Collection
    {
        return Product::search($query)
            ->where('restaurant_id', $restaurant->id)
            ->keys()
            ->map(fn (mixed $id): int => (int) $id)
            ->values();
    }

    /**
     * @param  Builder<Product>  $products
     * @param  Collection<int, int>  $rankedIds
     */
    private function applySort(Builder $products, string $sort, Collection $rankedIds): void
    {
        if ($sort === 'name') {
            $products->orderByRaw("json_extract(attribute_data, '$.name.value')");

            return;
        }

        if ($sort === 'price_asc' || $sort === 'price_desc') {
            $products->orderBy($this->minimumPriceQuery(), $sort === 'price_asc' ? 'asc' : 'desc');

            return;
        }

        if ($sort === 'relevance' && $rankedIds->isNotEmpty()) {
            // CASE ordering preserves the engine's rank and is portable
            // across sqlite and MySQL; ids are cast to int in matchingIds.
            $cases = $rankedIds->map(fn (int $id, int $position): string => "WHEN {$id} THEN {$position}")->implode(' ');
            $products->orderByRaw('CASE '.(new Product)->qualifyColumn('id')." {$cases} ELSE {$rankedIds->count()} END"); // @phpstan-ignore argument.type

            return;
        }

        $products->orderByDesc('created_at');
    }

    /**
     * Correlated subquery resolving a product's cheapest variant price in
     * cents, mirroring Lunar's own Product::prices() relation constraints.
     *
     * @return Builder<Price>
     */
    private function minimumPriceQuery(): Builder
    {
        return Price::query()
            ->selectRaw('MIN(price)')
            ->where('priceable_type', 'product_variant')
            ->whereIn(
                'priceable_id',
                ProductVariant::query()
                    ->select('id')
                    ->whereColumn('product_id', (new Product)->qualifyColumn('id')),
            );
    }
}
