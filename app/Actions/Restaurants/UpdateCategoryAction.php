<?php

namespace App\Actions\Restaurants;

use App\Models\Lunar\Collection;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Lunar\FieldTypes\Text;

class UpdateCategoryAction
{
    /**
     * Rename a category. Products attached to the category see the new
     * name immediately since they reference the same row through the
     * collection_product pivot — nothing is denormalized.
     *
     * @throws AuthorizationException when the actor may not update the category
     */
    public function handle(User $actor, Collection $category, string $name): Collection
    {
        throw_unless(
            $actor->can('update', $category),
            AuthorizationException::class,
        );

        $category->attribute_data = ($category->attribute_data ?? collect())->merge([
            'name' => new Text($name),
        ]);
        $category->save();

        return $category;
    }
}
