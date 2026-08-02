<?php

namespace App\Models\Lunar;

use App\Models\User;
use Database\Factories\Lunar\CustomerFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lunar\Models\Address;
use Lunar\Models\Cart;
use Lunar\Models\Customer as LunarCustomer;
use Lunar\Models\Order;

/**
 * A storefront customer, linked to an app User through customer_user.
 *
 * @property int $id
 * @property string|null $title
 * @property string $first_name
 * @property string $last_name
 * @property string|null $company_name
 * @property string|null $vat_no
 * @property Collection<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $users
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Address> $addresses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Order> $orders
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Cart> $carts
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
