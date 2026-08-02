<?php

namespace App\Actions\Checkout;

use Illuminate\Validation\ValidationException;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Cart;

class SetShippingOptionAction
{
    /**
     * Set the cart's shipping option by its manifest identifier.
     *
     * @throws ValidationException when the identifier is not an offered option
     */
    public function handle(Cart $cart, string $identifier): Cart
    {
        $option = ShippingManifest::getOption($cart, $identifier);

        throw_if(
            $option === null,
            ValidationException::withMessages(['shipping_option' => 'Unknown shipping option.']),
        );

        return $cart->setShippingOption($option);
    }
}
