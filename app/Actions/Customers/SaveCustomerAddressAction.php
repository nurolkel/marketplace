<?php

namespace App\Actions\Customers;

use App\Models\Lunar\Customer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Address;

class SaveCustomerAddressAction
{
    /**
     * Add an address to the customer's address book. When the address
     * is marked as a default (shipping or billing), any previous
     * default of the same kind is unset — there is only ever one.
     *
     * @param  array{first_name: string, last_name: string, line_one: string, city: string, line_two?: string|null, line_three?: string|null, company_name?: string|null, title?: string|null, state?: string|null, postcode?: string|null, country_id?: int|null, delivery_instructions?: string|null, contact_email?: string|null, contact_phone?: string|null, shipping_default?: bool, billing_default?: bool}  $data
     *
     * @throws AuthorizationException when the actor does not own the customer record
     */
    public function handle(User $actor, Customer $customer, array $data): Address
    {
        throw_unless(
            $actor->can('manageAddresses', $customer),
            AuthorizationException::class,
        );

        return DB::transaction(function () use ($customer, $data): Address {
            /** @var Address $address */
            $address = $customer->addresses()->create($data);

            foreach (['shipping_default', 'billing_default'] as $flag) {
                if ($data[$flag] ?? false) {
                    $customer->addresses()
                        ->whereKeyNot($address->id)
                        ->where($flag, true)
                        ->update([$flag => false]);
                }
            }

            return $address->refresh();
        });
    }
}
