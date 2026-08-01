<?php

namespace App\Actions\Restaurants;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class AttachRestaurantCategoriesAction
{
    /**
     * Tag a restaurant with categories from the platform taxonomy,
     * keeping any categories already attached.
     *
     * @param  array<int, int>  $categoryIds
     *
     * @throws AuthorizationException when the actor may not manage categories
     */
    public function handle(User $actor, Restaurant $restaurant, array $categoryIds): void
    {
        throw_unless(
            $actor->can('manageCategories', $restaurant),
            AuthorizationException::class,
        );

        $restaurant->categories()->syncWithoutDetaching($categoryIds);
    }
}
