<?php

namespace App\Actions\Checkout;

use Lunar\Models\Cart;

class RemoveCartLineAction
{
    /**
     * Remove a line from the cart by its line id.
     */
    public function handle(Cart $cart, int $cartLineId): Cart
    {
        return $cart->remove($cartLineId);
    }
}
