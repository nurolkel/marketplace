<?php

use App\Actions\Restaurants\AttachRestaurantCategoriesAction;
use App\Actions\Restaurants\DetachRestaurantCategoryAction;
use App\Enums\RestaurantRole;
use App\Models\Category;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Auth\Access\AuthorizationException;

test('owners can attach categories to their restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $owner = User::factory()->create();
    $restaurant->members()->attach($owner, ['role' => RestaurantRole::Owner->value]);

    $italian = Category::factory()->create(['name' => 'Italian', 'slug' => 'italian']);
    $pizzeria = Category::factory()->create(['name' => 'Pizzeria', 'slug' => 'pizzeria']);

    (new AttachRestaurantCategoriesAction)->handle($owner, $restaurant, [$italian->id, $pizzeria->id]);

    expect($restaurant->categories()->count())->toBe(2)
        ->and($restaurant->categories->pluck('slug')->all())->toContain('italian', 'pizzeria')
        ->and($italian->restaurants->first()->is($restaurant))->toBeTrue();
});

test('attaching categories keeps the ones already attached', function () {
    $restaurant = Restaurant::factory()->create();
    $owner = User::factory()->create();
    $restaurant->members()->attach($owner, ['role' => RestaurantRole::Owner->value]);

    $italian = Category::factory()->create(['slug' => 'italian']);
    $pizzeria = Category::factory()->create(['slug' => 'pizzeria']);
    $vegan = Category::factory()->create(['slug' => 'vegan']);

    $action = new AttachRestaurantCategoriesAction;
    $action->handle($owner, $restaurant, [$italian->id, $pizzeria->id]);
    $action->handle($owner, $restaurant->refresh(), [$vegan->id, $italian->id]);

    expect($restaurant->categories()->count())->toBe(3)
        ->and($restaurant->categories->pluck('slug')->all())
        ->toContain('italian', 'pizzeria', 'vegan');
});

test('managers can manage categories but staff and outsiders cannot', function () {
    $restaurant = Restaurant::factory()->create();
    $manager = User::factory()->create();
    $worker = User::factory()->create();
    $outsider = User::factory()->create();
    $restaurant->members()->attach($manager, ['role' => RestaurantRole::Manager->value]);
    $restaurant->members()->attach($worker, ['role' => RestaurantRole::Staff->value]);

    $category = Category::factory()->create();

    expect($manager->can('manageCategories', $restaurant))->toBeTrue()
        ->and($worker->can('manageCategories', $restaurant))->toBeFalse()
        ->and($outsider->can('manageCategories', $restaurant))->toBeFalse();

    (new AttachRestaurantCategoriesAction)->handle($manager, $restaurant, [$category->id]);
    expect($restaurant->categories()->count())->toBe(1);

    (new AttachRestaurantCategoriesAction)->handle($worker, $restaurant->refresh(), [$category->id]);
})->throws(AuthorizationException::class);

test('outsiders cannot attach categories', function () {
    $restaurant = Restaurant::factory()->create();
    $category = Category::factory()->create();

    (new AttachRestaurantCategoriesAction)->handle(User::factory()->create(), $restaurant, [$category->id]);
})->throws(AuthorizationException::class);

test('marketplace admins can manage categories on any restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create();

    (new AttachRestaurantCategoriesAction)->handle($admin, $restaurant, [$category->id]);

    expect($restaurant->categories()->count())->toBe(1);
});

test('owners can detach a category', function () {
    $restaurant = Restaurant::factory()->create();
    $owner = User::factory()->create();
    $restaurant->members()->attach($owner, ['role' => RestaurantRole::Owner->value]);

    $italian = Category::factory()->create(['slug' => 'italian']);
    $pizzeria = Category::factory()->create(['slug' => 'pizzeria']);
    $restaurant->categories()->attach([$italian->id, $pizzeria->id]);

    (new DetachRestaurantCategoryAction)->handle($owner, $restaurant, $italian);

    expect($restaurant->categories()->count())->toBe(1)
        ->and($restaurant->categories->first()->slug)->toBe('pizzeria');
});

test('staff cannot detach categories', function () {
    $restaurant = Restaurant::factory()->create();
    $worker = User::factory()->create();
    $restaurant->members()->attach($worker, ['role' => RestaurantRole::Staff->value]);

    $category = Category::factory()->create();
    $restaurant->categories()->attach($category);

    (new DetachRestaurantCategoryAction)->handle($worker, $restaurant, $category);
})->throws(AuthorizationException::class);

test('the category seeder creates the starter taxonomy', function () {
    $this->seed(CategorySeeder::class);

    expect(Category::count())->toBe(8)
        ->and(Category::pluck('slug')->all())
        ->toContain('italian', 'pizzeria', 'hot-plates', 'desserts', 'ice-cream', 'vegan', 'seafood', 'bakery');
});
