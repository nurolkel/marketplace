<?php

use App\Actions\Restaurants\AddStaffMemberAction;
use App\Actions\Restaurants\CreateRestaurantAction;
use App\Actions\Restaurants\RemoveStaffMemberAction;
use App\Actions\Restaurants\UpdateProductPriceAction;
use App\Actions\Restaurants\UpdateStaffRoleAction;
use App\Enums\RestaurantRole;
use App\Enums\RestaurantStatus;
use App\Exceptions\CannotRemoveLastOwnerException;
use App\Models\Lunar\Product;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\ProductVariant;

/**
 * Lunar's HasUrls trait generates a URL on product creation and
 * requires a default language to exist.
 */
function seedDefaultLanguage(): void
{
    Language::factory()->create(['code' => 'en', 'default' => true]);
}

test('creating a restaurant makes the creator its owner', function () {
    $creator = User::factory()->create();

    $restaurant = (new CreateRestaurantAction)->handle($creator, [
        'name' => 'Frozen Bistro',
        'description' => 'Ships nationwide',
    ]);

    expect($restaurant->slug)->toBe('frozen-bistro')
        ->and($restaurant->status)->toBe(RestaurantStatus::Draft)
        ->and($creator->hasRoleInRestaurant($restaurant, RestaurantRole::Owner))->toBeTrue()
        ->and($creator->isStaffOf($restaurant))->toBeTrue()
        ->and($restaurant->owners()->count())->toBe(1);
});

test('restaurant slugs are unique even for duplicate names', function () {
    $creator = User::factory()->create();
    $action = new CreateRestaurantAction;

    $first = $action->handle($creator, ['name' => 'Frozen Bistro']);
    $second = $action->handle($creator, ['name' => 'Frozen Bistro']);

    expect($first->slug)->toBe('frozen-bistro')
        ->and($second->slug)->toBe('frozen-bistro-2');
});

test('membership role helpers report roles correctly', function () {
    $restaurant = Restaurant::factory()->create();
    $owner = User::factory()->create();
    $worker = User::factory()->create();
    $outsider = User::factory()->create();

    $restaurant->members()->attach($owner, ['role' => RestaurantRole::Owner->value]);
    $restaurant->members()->attach($worker, ['role' => RestaurantRole::Staff->value]);

    expect($owner->roleInRestaurant($restaurant))->toBe(RestaurantRole::Owner)
        ->and($worker->roleInRestaurant($restaurant))->toBe(RestaurantRole::Staff)
        ->and($outsider->roleInRestaurant($restaurant))->toBeNull()
        ->and($outsider->isStaffOf($restaurant))->toBeFalse();
});

test('restaurant policy gates abilities by role', function () {
    $restaurant = Restaurant::factory()->create();
    $owner = User::factory()->create();
    $worker = User::factory()->create();
    $outsider = User::factory()->create();

    $restaurant->members()->attach($owner, ['role' => RestaurantRole::Owner->value]);
    $restaurant->members()->attach($worker, ['role' => RestaurantRole::Staff->value]);

    expect($owner->can('update', $restaurant))->toBeTrue()
        ->and($owner->can('manageStaff', $restaurant))->toBeTrue()
        ->and($worker->can('update', $restaurant))->toBeFalse()
        ->and($worker->can('manageStaff', $restaurant))->toBeFalse()
        ->and($worker->can('viewAnyStaff', $restaurant))->toBeTrue()
        ->and($outsider->can('viewAnyStaff', $restaurant))->toBeFalse();
});

test('adding a staff member requires the manageStaff ability', function () {
    $restaurant = Restaurant::factory()->create();
    $owner = User::factory()->create();
    $worker = User::factory()->create();
    $newcomer = User::factory()->create();

    $restaurant->members()->attach($owner, ['role' => RestaurantRole::Owner->value]);
    $restaurant->members()->attach($worker, ['role' => RestaurantRole::Staff->value]);

    $action = new AddStaffMemberAction;

    $action->handle($owner, $restaurant, $newcomer, RestaurantRole::Manager);
    expect($newcomer->hasRoleInRestaurant($restaurant->refresh(), RestaurantRole::Manager))->toBeTrue();

    $action->handle($worker, $restaurant, User::factory()->create(), RestaurantRole::Staff);
})->throws(AuthorizationException::class);

test('lunar products resolve to the app model and belong to a restaurant', function () {
    seedDefaultLanguage();

    $restaurant = Restaurant::factory()->create();
    $product = Product::factory()->create(['restaurant_id' => $restaurant->id]);

    $resolved = Lunar\Models\Product::query()->firstOrFail();

    expect($resolved)->toBeInstanceOf(Product::class)
        ->and($product->restaurant->is($restaurant))->toBeTrue()
        ->and($restaurant->products()->count())->toBe(1)
        ->and(Product::forRestaurant($restaurant)->count())->toBe(1)
        ->and(Product::factory()->marketplaceOwned()->create()->restaurant)->toBeNull();
});

test('staff can update a variant price for their restaurant product', function () {
    seedDefaultLanguage();
    Currency::factory()->create(['default' => true]);

    $restaurant = Restaurant::factory()->create();
    $worker = User::factory()->create();
    $restaurant->members()->attach($worker, ['role' => RestaurantRole::Staff->value]);

    $product = Product::factory()->create(['restaurant_id' => $restaurant->id]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

    $price = (new UpdateProductPriceAction)->handle($worker, $variant, 1299, 1599);

    expect($price->price->value)->toBe(1299)
        ->and($price->compare_price->value)->toBe(1599)
        ->and($price->min_quantity)->toBe(1)
        ->and($variant->prices()->count())->toBe(1);

    // Updating the same base tier overwrites instead of duplicating.
    (new UpdateProductPriceAction)->handle($worker, $variant->refresh(), 999);
    expect($variant->prices()->count())->toBe(1)
        ->and($variant->prices()->first()->price->value)->toBe(999);
});

test('non-staff cannot update a variant price', function () {
    seedDefaultLanguage();
    Currency::factory()->create(['default' => true]);

    $restaurant = Restaurant::factory()->create();
    $outsider = User::factory()->create();
    $product = Product::factory()->create(['restaurant_id' => $restaurant->id]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

    (new UpdateProductPriceAction)->handle($outsider, $variant, 1299);
})->throws(AuthorizationException::class);

test('owners can promote and demote staff members', function () {
    $restaurant = Restaurant::factory()->create();
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $restaurant->members()->attach($owner, ['role' => RestaurantRole::Owner->value]);
    $restaurant->members()->attach($member, ['role' => RestaurantRole::Staff->value]);

    $action = new UpdateStaffRoleAction;

    $action->handle($owner, $restaurant, $member, RestaurantRole::Manager);
    expect($member->roleInRestaurant($restaurant->refresh()))->toBe(RestaurantRole::Manager);

    $action->handle($owner, $restaurant, $member, RestaurantRole::Staff);
    expect($member->roleInRestaurant($restaurant->refresh()))->toBe(RestaurantRole::Staff);
});

test('staff cannot promote themselves', function () {
    $restaurant = Restaurant::factory()->create();
    $worker = User::factory()->create();

    $restaurant->members()->attach($worker, ['role' => RestaurantRole::Staff->value]);

    (new UpdateStaffRoleAction)->handle($worker, $restaurant, $worker, RestaurantRole::Owner);
})->throws(AuthorizationException::class);

test('the sole owner cannot be demoted', function () {
    $restaurant = Restaurant::factory()->create();
    $owner = User::factory()->create();

    $restaurant->members()->attach($owner, ['role' => RestaurantRole::Owner->value]);

    (new UpdateStaffRoleAction)->handle($owner, $restaurant, $owner, RestaurantRole::Staff);
})->throws(CannotRemoveLastOwnerException::class);

test('an owner can be demoted once another owner exists', function () {
    $restaurant = Restaurant::factory()->create();
    $owner = User::factory()->create();
    $coOwner = User::factory()->create();

    $restaurant->members()->attach($owner, ['role' => RestaurantRole::Owner->value]);
    $restaurant->members()->attach($coOwner, ['role' => RestaurantRole::Owner->value]);

    (new UpdateStaffRoleAction)->handle($owner, $restaurant, $coOwner, RestaurantRole::Manager);

    expect($coOwner->roleInRestaurant($restaurant->refresh()))->toBe(RestaurantRole::Manager)
        ->and($restaurant->owners()->count())->toBe(1);
});

test('owners can remove staff members', function () {
    $restaurant = Restaurant::factory()->create();
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $restaurant->members()->attach($owner, ['role' => RestaurantRole::Owner->value]);
    $restaurant->members()->attach($member, ['role' => RestaurantRole::Staff->value]);

    (new RemoveStaffMemberAction)->handle($owner, $restaurant, $member);

    expect($member->isStaffOf($restaurant->refresh()))->toBeFalse()
        ->and($restaurant->members()->count())->toBe(1);
});

test('the sole owner cannot be removed', function () {
    $restaurant = Restaurant::factory()->create();
    $owner = User::factory()->create();

    $restaurant->members()->attach($owner, ['role' => RestaurantRole::Owner->value]);

    (new RemoveStaffMemberAction)->handle($owner, $restaurant, $owner);
})->throws(CannotRemoveLastOwnerException::class);

test('staff cannot remove other members', function () {
    $restaurant = Restaurant::factory()->create();
    $owner = User::factory()->create();
    $worker = User::factory()->create();
    $colleague = User::factory()->create();

    $restaurant->members()->attach($owner, ['role' => RestaurantRole::Owner->value]);
    $restaurant->members()->attach($worker, ['role' => RestaurantRole::Staff->value]);
    $restaurant->members()->attach($colleague, ['role' => RestaurantRole::Staff->value]);

    (new RemoveStaffMemberAction)->handle($worker, $restaurant, $colleague);
})->throws(AuthorizationException::class);

test('restaurant product policy allows staff but not outsiders', function () {
    seedDefaultLanguage();

    $restaurant = Restaurant::factory()->create();
    $worker = User::factory()->create();
    $outsider = User::factory()->create();
    $restaurant->members()->attach($worker, ['role' => RestaurantRole::Staff->value]);

    $product = Product::factory()->create(['restaurant_id' => $restaurant->id]);
    $marketplaceProduct = Product::factory()->marketplaceOwned()->create();

    expect($worker->can('update', $product))->toBeTrue()
        ->and($outsider->can('update', $product))->toBeFalse()
        ->and($worker->can('update', $marketplaceProduct))->toBeFalse()
        ->and(Gate::forUser($worker)->allows('delete', $product))->toBeTrue();
});
