<?php

namespace App\Actions\Search;

use App\Enums\RestaurantStatus;
use App\Models\Restaurant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SearchRestaurantsAction
{
    /**
     * Sorts customers may request. Anything else falls back to relevance
     * so raw input never reaches an orderBy clause.
     */
    private const array SORTS = ['relevance', 'name', 'newest'];

    /**
     * Search active restaurants by text, optionally filtered to a
     * category slug, with a whitelisted sort. A blank query browses all
     * active restaurants; relevance without a query means newest first.
     *
     * @return LengthAwarePaginator<int, Restaurant>
     */
    public function handle(?string $query, ?string $categorySlug = null, string $sort = 'relevance', int $perPage = 15): LengthAwarePaginator
    {
        $query = trim((string) $query);
        $sort = in_array($sort, self::SORTS, true) ? $sort : 'relevance';

        $restaurants = Restaurant::query()->where('status', RestaurantStatus::Active);

        $rankedIds = collect();
        if ($query !== '') {
            $rankedIds = $this->matchingIds($query);
            $restaurants->whereIn((new Restaurant)->qualifyColumn('id'), $rankedIds);
        }

        if ($categorySlug !== null) {
            $restaurants->whereHas('categories', fn (Builder $categories): Builder => $categories->where('slug', $categorySlug));
        }

        $this->applySort($restaurants, $sort, $rankedIds);

        return $restaurants->paginate($perPage);
    }

    /**
     * Ranked ids from the Scout engine for the given query.
     *
     * @return Collection<int, int>
     */
    private function matchingIds(string $query): Collection
    {
        return Restaurant::search($query)->keys()->map(fn (mixed $id): int => (int) $id)->values();
    }

    /**
     * @param  Builder<Restaurant>  $restaurants
     * @param  Collection<int, int>  $rankedIds
     */
    private function applySort(Builder $restaurants, string $sort, Collection $rankedIds): void
    {
        if ($sort === 'name') {
            $restaurants->orderBy('name');

            return;
        }

        if ($sort === 'relevance' && $rankedIds->isNotEmpty()) {
            // CASE ordering preserves the engine's rank and is portable
            // across sqlite and MySQL; ids are cast to int in matchingIds.
            $cases = $rankedIds->map(fn (int $id, int $position): string => "WHEN {$id} THEN {$position}")->implode(' ');
            $restaurants->orderByRaw('CASE '.(new Restaurant)->qualifyColumn('id')." {$cases} ELSE {$rankedIds->count()} END"); // @phpstan-ignore argument.type

            return;
        }

        $restaurants->orderByDesc('created_at');
    }
}
