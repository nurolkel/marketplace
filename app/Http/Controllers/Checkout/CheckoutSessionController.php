<?php

namespace App\Http\Controllers\Checkout;

use App\Actions\Checkout\CreateCheckoutSessionAction;
use App\Actions\Checkout\GetOrCreateCartAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lunar\Models\Cart;
use Lunar\Models\CartAddress;

class CheckoutSessionController extends Controller
{
    public function __construct(
        private GetOrCreateCartAction $getOrCreateCart,
        private CreateCheckoutSessionAction $createCheckoutSession,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        $cart = $this->getOrCreateCart->handle($user);

        $url = $this->createCheckoutSession->handle(
            $cart,
            $user instanceof User ? $user->email : $this->contactEmail($cart),
        );

        return response()->json(['url' => $url], 201);
    }

    /**
     * The guest's contact email from the cart addresses, if the
     * address step has run.
     */
    private function contactEmail(Cart $cart): ?string
    {
        $address = $cart->shippingAddress ?? $cart->billingAddress;

        return $address instanceof CartAddress ? $address->contact_email : null;
    }
}
