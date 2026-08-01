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
