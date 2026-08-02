<?php

use App\Actions\Restaurants\UpdateRestaurantCommissionAction;
use App\Enums\RestaurantRole;
use App\Enums\UserType;
use App\Models\CommissionTier;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\CommissionTierSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(CommissionTierSeeder::class);

    $this->restaurant = Restaurant::factory()->create();
    $this->owner = User::factory()->create();
    $this->restaurant->members()->attach($this->owner, ['role' => RestaurantRole::Owner->value]);
    $this->staff = User::factory()->create();
    $this->restaurant->members()->attach($this->staff, ['role' => RestaurantRole::Staff->value]);
    $this->outsider = User::factory()->create();
    $this->admin = User::factory()->create(['type' => UserType::Admin]);
});

test('restaurant staff can see commission details; outsiders cannot', function () {
    expect($this->owner->can('viewCommission', $this->restaurant))->toBeTrue()
        ->and($this->staff->can('viewCommission', $this->restaurant))->toBeTrue()
        ->and($this->outsider->can('viewCommission', $this->restaurant))->toBeFalse()
        ->and($this->admin->can('viewCommission', $this->restaurant))->toBeTrue();
});

test('only platform admins can change commission terms', function () {
    expect($this->owner->can('manageCommission', $this->restaurant))->toBeFalse()
        ->and($this->staff->can('manageCommission', $this->restaurant))->toBeFalse()
        ->and($this->outsider->can('manageCommission', $this->restaurant))->toBeFalse()
        ->and($this->admin->can('manageCommission', $this->restaurant))->toBeTrue();
});

test('restaurants without a tier pay the platform standard rate', function () {
    expect($this->restaurant->effectiveCommissionRate())->toBe(1500);
});

test('an assigned tier drives the effective rate', function () {
    $preferred = CommissionTier::where('slug', 'preferred')->firstOrFail();

    $restaurant = app(UpdateRestaurantCommissionAction::class)
        ->handle($this->admin, $this->restaurant, commissionTierId: $preferred->id);

    expect($restaurant->commission_tier_id)->toBe($preferred->id)
        ->and($restaurant->effectiveCommissionRate())->toBe(1000);
});

test('a custom rate override beats any tier', function () {
    $restaurant = app(UpdateRestaurantCommissionAction::class)
        ->handle($this->admin, $this->restaurant, commissionRate: 300);

    expect($restaurant->effectiveCommissionRate())->toBe(300);
});

test('clearing the override returns the restaurant to its tier rate', function () {
    $action = app(UpdateRestaurantCommissionAction::class);
    $preferred = CommissionTier::where('slug', 'preferred')->firstOrFail();

    $restaurant = $action->handle($this->admin, $this->restaurant, commissionRate: 300);
    expect($restaurant->effectiveCommissionRate())->toBe(300);

    $restaurant = $action->handle($this->admin, $restaurant, commissionTierId: $preferred->id);
    expect($restaurant->effectiveCommissionRate())->toBe(1000);
});

test('rates above 100 percent are rejected', function () {
    app(UpdateRestaurantCommissionAction::class)
        ->handle($this->admin, $this->restaurant, commissionRate: 10001);
})->throws(ValidationException::class);

test('assigning a missing tier is rejected', function () {
    app(UpdateRestaurantCommissionAction::class)
        ->handle($this->admin, $this->restaurant, commissionTierId: 999);
})->throws(ModelNotFoundException::class);

test('restaurants cannot change their own commission', function () {
    app(UpdateRestaurantCommissionAction::class)
        ->handle($this->owner, $this->restaurant, commissionRate: 0);
})->throws(AuthorizationException::class);

test('the seeder provides the starting scale', function () {
    expect(CommissionTier::count())->toBe(3)
        ->and(CommissionTier::default()->slug)->toBe('standard')
        ->and(CommissionTier::where('slug', 'partner')->value('rate'))->toBe(500);
});
