<?php

namespace App\Actions\Checkout;

use Lunar\Models\Cart;

class SetCheckoutAddressesAction
{
    /**
     * Set the cart's shipping and billing addresses. The shipping
     * address doubles as the billing address when none is given.
     *
     * @param  array{first_name: string, last_name: string, line_one: string, city: string, postcode: string, country_id: int, line_two?: string|null, state?: string|null, contact_email?: string|null, contact_phone?: string|null}  $shipping
     * @param  array{first_name: string, last_name: string, line_one: string, city: string, postcode: string, country_id: int, line_two?: string|null, state?: string|null, contact_email?: string|null, contact_phone?: string|null}|null  $billing
     */
    public function handle(Cart $cart, array $shipping, ?array $billing = null): Cart
    {
        $cart->addAddress($shipping, 'shipping');
        $cart->addAddress($billing ?? $shipping, 'billing');

        return $cart->refresh();
    }
}
