<?php

namespace App\Actions\Restaurants;

use App\Models\Lunar\Collection;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Lunar\FieldTypes\Text;
use Lunar\Models\CollectionGroup;

class CreateCategoryAction
{
    /**
     * Create a category. Pass a restaurant for a restaurant-owned
     * category, or null for a marketplace-wide one (admins only).
     *
     * @throws AuthorizationException when the actor may not create categories for the given owner
     */
    public function handle(User $actor, ?Restaurant $restaurant, string $name): Collection
    {
        $this->authorize($actor, $restaurant);

        $category = new Collection;
        $category->group()->associate($this->defaultGroup());
        $category->restaurant()->associate($restaurant);
        $category->attribute_data = collect([
            'name' => new Text($name),
        ]);
        $category->save();

        return $category;
    }

    private function authorize(User $actor, ?Restaurant $restaurant): void
    {
        $allowed = $restaurant === null
            ? $actor->isMarketplaceAdmin()
            : $actor->isStaffOf($restaurant);

        throw_unless($allowed, AuthorizationException::class);
    }

    /**
     * The shared collection group all storefront categories live in.
     */
    private function defaultGroup(): CollectionGroup
    {
        return CollectionGroup::firstOrCreate(
            ['handle' => 'storefront-categories'],
            ['name' => 'Storefront Categories'],
        );
    }
}
