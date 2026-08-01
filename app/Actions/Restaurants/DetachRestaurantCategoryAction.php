<?php

namespace App\Actions\Restaurants;

use App\Models\Category;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class DetachRestaurantCategoryAction
{
    /**
     * Remove a single category from a restaurant.
     *
     * @throws AuthorizationException when the actor may not manage categories
     */
    public function handle(User $actor, Restaurant $restaurant, Category $category): void
    {
        throw_unless(
            $actor->can('manageCategories', $restaurant),
            AuthorizationException::class,
        );

        $restaurant->categories()->detach($category);
    }
}
