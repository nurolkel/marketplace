<?php

namespace App\Actions\Checkout;

use Lunar\Models\Cart;
use Lunar\Models\ProductVariant;

class AddCartLineAction
{
    /**
     * Add a product variant to the cart, merging with an existing line
     * for the same purchasable when present.
     */
    public function handle(Cart $cart, int $purchasableId, int $quantity): Cart
    {
        $purchasable = ProductVariant::findOrFail($purchasableId);

        return $cart->add($purchasable, $quantity);
    }
}
