<?php

namespace App\Http\Controllers\Checkout;

use App\Actions\Checkout\GetOrCreateCartAction;
use App\Actions\Checkout\SetCheckoutAddressesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\UpdateCheckoutAddressRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class CheckoutAddressController extends Controller
{
    public function __construct(
        private GetOrCreateCartAction $getOrCreateCart,
        private SetCheckoutAddressesAction $setCheckoutAddresses,
    ) {}

    public function __invoke(UpdateCheckoutAddressRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        $cart = $this->setCheckoutAddresses->handle(
            $this->getOrCreateCart->handle($user),
            $request->validated('shipping'),
            $request->validated('billing'),
        );

        return response()->json([
            'cart_id' => $cart->id,
            'shipping_address_id' => $cart->shippingAddress?->getKey(),
            'billing_address_id' => $cart->billingAddress?->getKey(),
        ]);
    }
}
