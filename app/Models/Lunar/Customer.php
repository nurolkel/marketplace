<?php

namespace App\Models\Lunar;

use Database\Factories\Lunar\CustomerFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Lunar\Models\Address;
use Lunar\Models\Customer as LunarCustomer;

/**
 * A storefront customer, linked to an app User through customer_user.
 *
 * @property int $id
 * @property string|null $title
 * @property string $first_name
 * @property string $last_name
 * @property string|null $company_name
 * @property string|null $vat_no
 * @property \Illuminate\Support\Collection<string, mixed>|null $meta
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Address> $addresses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Lunar\Models\Order> $orders
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Lunar\Models\Cart> $carts
 * @property-read Address|null $defaultShippingAddress
 * @property-read Address|null $defaultBillingAddress
 */
class Customer extends LunarCustomer
{
    /**
     * Return a new factory instance for the model.
     */
    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }

    /**
     * The customer's default shipping address, if one is set.
     *
     * @return HasOne<Address, $this>
     */
    public function defaultShippingAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('shipping_default', true);
    }

    /**
     * The customer's default billing address, if one is set.
     *
     * @return HasOne<Address, $this>
     */
    public function defaultBillingAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('billing_default', true);
    }

    /**
     * The customer's full name.
     */
    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
