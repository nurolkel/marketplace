<?php

namespace App\Actions\Customers;

use App\Models\Lunar\Customer;
use App\Models\User;

class GetOrCreateCustomerAction
{
    /**
     * Return the user's linked Lunar customer, creating and linking one
     * on first use. Safe to call any number of times.
     */
    public function handle(User $user): Customer
    {
        $existing = $user->lunarCustomer()->first();

        if ($existing !== null) {
            return $existing;
        }

        [$firstName, $lastName] = $this->splitName($user->name);

        $customer = Customer::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);

        $user->lunarCustomer()->attach($customer);

        return $customer;
    }

    /**
     * Return the guest's customer record, matched by the email stored
     * in meta on first checkout. Guests have no user account, so the
     * record is never linked to one; a later registration attaches a
     * fresh account-linked customer instead.
     */
    public function forGuest(string $email, string $firstName, string $lastName): Customer
    {
        /** @var Customer|null $existing */
        $existing = Customer::query()->where('meta->email', $email)->first();

        if ($existing !== null) {
            return $existing;
        }

        return Customer::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'meta' => ['email' => $email],
        ]);
    }

    /**
     * Split a display name into first and last name parts.
     *
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];

        return [
            $parts[0] ?? 'Customer',
            $parts[1] ?? '',
        ];
    }
}
