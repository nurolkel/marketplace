<?php

namespace App\Actions\Checkout;

use App\Exceptions\PaymentAuthorizationFailedException;
use App\Models\Lunar\Order;
use Lunar\Facades\CartSession;
use Lunar\Facades\Payments;
use Lunar\Models\Cart;
use Stripe\StripeClient;

class PlaceOrderAction
{
    public function __construct(
        private StripeClient $stripe,
    ) {}

    /**
     * Authorize the paid Checkout Session and create the order from
     * the cart. The order creation pipeline splits it into per-restaurant
     * sub-orders automatically.
     *
     * @throws PaymentAuthorizationFailedException when the payment cannot be authorized
     */
    public function handle(Cart $cart, string $checkoutSessionId): Order
    {
        $session = $this->stripe->checkout->sessions->retrieve($checkoutSessionId);

        if (! is_string($session->payment_intent) || $session->payment_intent === '') {
            throw new PaymentAuthorizationFailedException('This checkout session has no payment.');
        }

        $response = Payments::driver('stripe')
            ->withData(['payment_intent' => $session->payment_intent])
            ->cart($cart->calculate())
            ->authorize();

        if (! $response->success) {
            throw new PaymentAuthorizationFailedException(
                $response->message ?: 'The payment could not be authorized.'
            );
        }

        CartSession::forget();

        return Order::query()->findOrFail((int) $response->orderId);
    }
}
