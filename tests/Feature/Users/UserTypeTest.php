<?php

use App\Enums\RestaurantRole;
use App\Enums\UserType;
use App\Models\Restaurant;
use App\Models\User;

test('new users default to the customer type', function () {
    $user = User::factory()->create();

    expect($user->type)->toBe(UserType::Customer)
        ->and($user->isCustomer())->toBeTrue()
        ->and($user->isMarketplaceAdmin())->toBeFalse()
        ->and($user->isRestaurantStaff())->toBeFalse();
});

test('the type is cast to the UserType enum', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->type)->toBeInstanceOf(UserType::class)
        ->and($admin->type->label())->toBe('Admin')
        ->and($admin->isMarketplaceAdmin())->toBeTrue()
        ->and($admin->isCustomer())->toBeFalse();
});

test('restaurant staff is derived from membership, not the type column', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create();

    $restaurant->members()->attach($user, ['role' => RestaurantRole::Staff->value]);

    expect($user->isRestaurantStaff())->toBeTrue()
        ->and($user->type)->toBe(UserType::Customer);
});

test('marketplace admins pass every gate check', function () {
    $admin = User::factory()->admin()->create();
    $restaurant = Restaurant::factory()->create();

    expect($admin->can('manageStaff', $restaurant))->toBeTrue()
        ->and($admin->can('update', $restaurant))->toBeTrue()
        ->and($admin->can('viewAnyStaff', $restaurant))->toBeTrue();
});

test('regular users still go through policies', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create();

    expect($user->can('manageStaff', $restaurant))->toBeFalse();
});

test('the type column is not mass assignable', function () {
    $user = User::create([
        'name' => 'Sneaky Shopper',
        'email' => 'sneaky@example.com',
        'password' => 'password',
        'type' => 'admin',
    ]);

    expect($user->refresh()->type)->toBe(UserType::Customer);
});
