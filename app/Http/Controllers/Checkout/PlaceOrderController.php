<?php

namespace App\Http\Controllers\Checkout;

use App\Actions\Checkout\GetOrCreateCartAction;
use App\Actions\Checkout\PlaceOrderAction;
use App\Actions\Customers\GetOrCreateCustomerAction;
use App\Exceptions\PaymentAuthorizationFailedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\PlaceOrderRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Lunar\Models\Cart;
use Lunar\Models\CartAddress;

class PlaceOrderController extends Controller
{
    public function __construct(
        private GetOrCreateCartAction $getOrCreateCart,
        private GetOrCreateCustomerAction $getOrCreateCustomer,
        private PlaceOrderAction $placeOrder,
    ) {}

    public function __invoke(PlaceOrderRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        $cart = $this->getOrCreateCart->handle($user);

        if ($user === null && $cart->customer_id === null) {
            $this->attachGuestCustomer($cart);
        }

        try {
            $order = $this->placeOrder->handle(
                $cart,
                $request->string('session_id')->toString(),
            );
        } catch (PaymentAuthorizationFailedException $e) {
            return response()->json(['message' => $e->getMessage()], 402);
        }

        return response()->json([
            'order_id' => $order->id,
            'reference' => $order->reference,
            'status' => $order->status,
        ], 201);
    }

    /**
     * Link a guest customer record to the cart from the checkout
     * address, so the order carries an identifiable customer. The
     * address step already guarantees a contact email for guests;
     * without an address we let Stripe collect the email instead.
     */
    private function attachGuestCustomer(Cart $cart): void
    {
        $address = $cart->shippingAddress ?? $cart->billingAddress;

        if (! $address instanceof CartAddress || $address->contact_email === null) {
            return;
        }

        $customer = $this->getOrCreateCustomer->forGuest(
            $address->contact_email,
            $address->first_name ?? '',
            $address->last_name ?? '',
        );

        $cart->update(['customer_id' => $customer->id]);
    }
}
