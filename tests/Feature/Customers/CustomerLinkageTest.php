<?php

use App\Actions\Customers\GetOrCreateCustomerAction;
use App\Actions\Customers\SaveCustomerAddressAction;
use App\Models\Lunar\Customer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

test('registering creates and links a lunar customer', function () {
    $this->post('/register', [
        'name' => 'Nadia Frost',
        'email' => 'nadia@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'nadia@example.com')->firstOrFail();
    $customer = $user->lunarCustomer()->first();

    expect($customer)->not->toBeNull()
        ->and($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->first_name)->toBe('Nadia')
        ->and($customer->last_name)->toBe('Frost')
        ->and($customer->fullName())->toBe('Nadia Frost')
        ->and(Customer::count())->toBe(1);
});

test('get or create customer is idempotent', function () {
    $user = User::factory()->create();
    $action = new GetOrCreateCustomerAction;

    $first = $action->handle($user);
    $second = $action->handle($user);

    expect($second->id)->toBe($first->id)
        ->and(Customer::count())->toBe(1)
        ->and($user->lunarCustomer()->count())->toBe(1);
});

test('single word names still produce a customer', function () {
    $user = User::factory()->create(['name' => 'Madonna']);

    $customer = (new GetOrCreateCustomerAction)->handle($user);

    expect($customer->first_name)->toBe('Madonna')
        ->and($customer->last_name)->toBe('')
        ->and($customer->fullName())->toBe('Madonna');
});

test('customers can add addresses to their own address book', function () {
    $user = User::factory()->create();
    $customer = (new GetOrCreateCustomerAction)->handle($user);

    $address = (new SaveCustomerAddressAction)->handle($user, $customer, [
        'first_name' => 'Nadia',
        'last_name' => 'Frost',
        'line_one' => '12 Icebox Lane',
        'city' => 'Portland',
        'state' => 'OR',
        'postcode' => '97201',
        'shipping_default' => true,
    ]);

    expect($customer->addresses()->count())->toBe(1)
        ->and($customer->defaultShippingAddress->id)->toBe($address->id);
});

test('setting a new default unsets the previous one of the same kind', function () {
    $user = User::factory()->create();
    $customer = (new GetOrCreateCustomerAction)->handle($user);
    $action = new SaveCustomerAddressAction;

    $first = $action->handle($user, $customer, [
        'first_name' => 'Nadia', 'last_name' => 'Frost',
        'line_one' => '12 Icebox Lane', 'city' => 'Portland',
        'shipping_default' => true, 'billing_default' => true,
    ]);

    $second = $action->handle($user, $customer, [
        'first_name' => 'Nadia', 'last_name' => 'Frost',
        'line_one' => '99 Glacier Way', 'city' => 'Seattle',
        'shipping_default' => true,
    ]);

    expect($first->refresh()->shipping_default)->toBeFalse()
        ->and($first->billing_default)->toBeTrue()
        ->and($second->shipping_default)->toBeTrue()
        ->and($customer->defaultShippingAddress->id)->toBe($second->id)
        ->and($customer->defaultBillingAddress->id)->toBe($first->id);
});

test('users cannot manage addresses for another customers record', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $customer = (new GetOrCreateCustomerAction)->handle($owner);

    (new SaveCustomerAddressAction)->handle($intruder, $customer, [
        'first_name' => 'Bad', 'last_name' => 'Actor',
        'line_one' => '1 Nowhere St', 'city' => 'Nowhere',
    ]);
})->throws(AuthorizationException::class);

test('admins can manage any customers addresses', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $customer = (new GetOrCreateCustomerAction)->handle($owner);

    $address = (new SaveCustomerAddressAction)->handle($admin, $customer, [
        'first_name' => 'Admin', 'last_name' => 'Assist',
        'line_one' => '1 Helper Rd', 'city' => 'Denver',
    ]);

    expect($customer->addresses()->count())->toBe(1)
        ->and($address->city)->toBe('Denver');
});
