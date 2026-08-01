<?php

namespace App\Actions\Restaurants;

use App\Models\Lunar\Product;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class SyncProductCategoriesAction
{
    /**
     * Replace a product's category assignments with the given category
     * IDs. Categories not in the list are detached; attached ones keep
     * their pivot position.
     *
     * @param  array<int, int>  $categoryIds
     *
     * @throws AuthorizationException when the actor may not update the product
     */
    public function handle(User $actor, Product $product, array $categoryIds): void
    {
        throw_unless(
            $actor->can('update', $product),
            AuthorizationException::class,
        );

        $product->collections()->sync($categoryIds);
    }
}
