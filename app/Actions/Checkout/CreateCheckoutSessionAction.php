<?php

namespace App\Actions\Checkout;

use Illuminate\Validation\ValidationException;
use Lunar\Base\Purchasable;
use Lunar\Models\Cart;
use Lunar\Models\CartLine;
use Lunar\Models\Currency;
use Stripe\StripeClient;

class CreateCheckoutSessionAction
{
    public function __construct(
        private StripeClient $stripe,
    ) {}

    /**
     * Create a Stripe Checkout Session for the cart and return its
     * hosted-page URL. The customer pays on Stripe's page, so card
     * data never touches our server. The session's payment intent id
     * is stored in the cart meta so the webhook and the return flow
     * can reconcile the payment afterwards.
     *
     * @param  string|null  $customerEmail  Prefills Stripe's email field so receipts reach the payer (the guest's contact email or the account email).
     *
     * @throws ValidationException when the cart has no payable total
     */
    public function handle(Cart $cart, ?string $customerEmail = null): string
    {
        $cart = $cart->calculate();

        if ($cart->total->value <= 0) {
            throw ValidationException::withMessages([
                'cart' => 'Your cart is empty.',
            ]);
        }

        /** @var Currency $currency */
        $currency = $cart->currency;

        $lineItems = [];

        /** @var CartLine $line */
        foreach ($cart->lines as $line) {
            $purchasable = $line->purchasable;

            $lineItems[] = [
                'quantity' => $line->quantity,
                'price_data' => [
                    'currency' => $currency->code,
                    'unit_amount' => $line->unitPrice->value,
                    'product_data' => [
                        'name' => $purchasable instanceof Purchasable ? $purchasable->getDescription() : 'Item',
                    ],
                ],
            ];
        }

        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => $lineItems,
            'customer_email' => $customerEmail,
            'success_url' => config('services.stripe.checkout.success_url'),
            'cancel_url' => config('services.stripe.checkout.cancel_url'),
        ]);

        $cart->update([
            'meta' => [
                ...(array) ($cart->getAttribute('meta') ?? []),
                'checkout_session' => $session->id,
                'payment_intent' => $session->payment_intent,
            ],
        ]);

        return $session->url;
    }
}
