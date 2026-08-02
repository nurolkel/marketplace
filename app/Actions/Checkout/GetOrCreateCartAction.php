<?php

namespace App\Actions\Checkout;

use App\Actions\Customers\GetOrCreateCustomerAction;
use App\Models\User;
use Lunar\Facades\CartSession;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Currency;

class GetOrCreateCartAction
{
    public function __construct(
        private GetOrCreateCustomerAction $getOrCreateCustomer,
    ) {}

    /**
     * Return the current session cart, creating one for the default
     * channel and currency when the session has none.
     */
    public function handle(?User $user = null): Cart
    {
        if ($cart = CartSession::current()) {
            return $cart;
        }

        $cart = Cart::create([
            'channel_id' => Channel::getDefault()->id,
            'currency_id' => Currency::getDefault()->id,
            'user_id' => $user?->id,
            'customer_id' => $user ? $this->getOrCreateCustomer->handle($user)->id : null,
        ]);

        CartSession::use($cart);

        return $cart;
    }
}
