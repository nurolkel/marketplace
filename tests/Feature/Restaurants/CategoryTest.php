<?php

use App\Actions\Restaurants\CreateCategoryAction;
use App\Actions\Restaurants\SyncProductCategoriesAction;
use App\Actions\Restaurants\UpdateCategoryAction;
use App\Enums\RestaurantRole;
use App\Models\Lunar\Collection;
use App\Models\Lunar\Product;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Language;

/**
 * Lunar's HasUrls trait generates a URL on collection/product creation
 * and requires a default language to exist.
 */
beforeEach(fn () => Language::factory()->create(['code' => 'en', 'default' => true]));

test('restaurant staff can create a category for their restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $worker = User::factory()->create();
    $restaurant->members()->attach($worker, ['role' => RestaurantRole::Staff->value]);

    $category = (new CreateCategoryAction)->handle($worker, $restaurant, 'Frozen Pizzas');

    expect($category->displayName())->toBe('Frozen Pizzas')
        ->and($category->restaurant->is($restaurant))->toBeTrue()
        ->and($category->group->handle)->toBe('storefront-categories')
        ->and(Collection::forRestaurant($restaurant)->count())->toBe(1);
});

test('the shared category group is created once and reused', function () {
    $restaurant = Restaurant::factory()->create();
    $worker = User::factory()->create();
    $restaurant->members()->attach($worker, ['role' => RestaurantRole::Owner->value]);

    (new CreateCategoryAction)->handle($worker, $restaurant, 'Appetizers');
    (new CreateCategoryAction)->handle($worker, $restaurant, 'Desserts');

    expect(CollectionGroup::count())->toBe(1);
});

test('outsiders cannot create a category for someone elses restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $outsider = User::factory()->create();

    (new CreateCategoryAction)->handle($outsider, $restaurant, 'Frozen Pizzas');
})->throws(AuthorizationException::class);

test('only admins can create marketplace-wide categories', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create();

    $category = (new CreateCategoryAction)->handle($admin, null, 'Staff Picks');
    expect($category->restaurant)->toBeNull();

    (new CreateCategoryAction)->handle($customer, null, 'Sneaky Picks');
})->throws(AuthorizationException::class);

test('renaming a category updates it for every attached product', function () {
    $restaurant = Restaurant::factory()->create();
    $worker = User::factory()->create();
    $restaurant->members()->attach($worker, ['role' => RestaurantRole::Owner->value]);

    $category = (new CreateCategoryAction)->handle($worker, $restaurant, 'Pasta');
    $product = Product::factory()->create(['restaurant_id' => $restaurant->id]);
    $product->collections()->attach($category);

    (new UpdateCategoryAction)->handle($worker, $category, 'Fresh Pasta');

    // The product references the same row: no denormalized copy to sync.
    expect($category->refresh()->displayName())->toBe('Fresh Pasta')
        ->and($product->collections()->first()->displayName())->toBe('Fresh Pasta')
        ->and($product->collections()->count())->toBe(1);
});

test('staff cannot rename another restaurants category', function () {
    $ownerRestaurant = Restaurant::factory()->create();
    $otherRestaurant = Restaurant::factory()->create();
    $owner = User::factory()->create();
    $otherWorker = User::factory()->create();

    $ownerRestaurant->members()->attach($owner, ['role' => RestaurantRole::Owner->value]);
    $otherRestaurant->members()->attach($otherWorker, ['role' => RestaurantRole::Staff->value]);

    $category = (new CreateCategoryAction)->handle($owner, $ownerRestaurant, 'Pasta');

    (new UpdateCategoryAction)->handle($otherWorker, $category, 'Hijacked');
})->throws(AuthorizationException::class);

test('staff can assign categories to their restaurants products', function () {
    $restaurant = Restaurant::factory()->create();
    $worker = User::factory()->create();
    $restaurant->members()->attach($worker, ['role' => RestaurantRole::Staff->value]);

    $product = Product::factory()->create(['restaurant_id' => $restaurant->id]);
    $pasta = (new CreateCategoryAction)->handle($worker, $restaurant, 'Pasta');
    $frozen = (new CreateCategoryAction)->handle($worker, $restaurant, 'Frozen Meals');

    $action = new SyncProductCategoriesAction;

    $action->handle($worker, $product, [$pasta->id, $frozen->id]);
    expect($product->collections()->count())->toBe(2);

    // Re-syncing with a subset detaches the rest.
    $action->handle($worker, $product->refresh(), [$frozen->id]);
    expect($product->collections()->pluck('lunar_collections.id')->all())->toBe([$frozen->id]);
});

test('outsiders cannot assign categories to a restaurant product', function () {
    $restaurant = Restaurant::factory()->create();
    $outsider = User::factory()->create();
    $product = Product::factory()->create(['restaurant_id' => $restaurant->id]);
    $category = Collection::factory()->create(['restaurant_id' => $restaurant->id]);

    (new SyncProductCategoriesAction)->handle($outsider, $product, [$category->id]);
})->throws(AuthorizationException::class);
