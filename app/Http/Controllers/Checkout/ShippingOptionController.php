<?php

namespace App\Http\Controllers\Checkout;

use App\Actions\Checkout\GetOrCreateCartAction;
use App\Actions\Checkout\SetShippingOptionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\SetShippingOptionRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ShippingOptionController extends Controller
{
    public function __construct(
        private GetOrCreateCartAction $getOrCreateCart,
        private SetShippingOptionAction $setShippingOption,
    ) {}

    public function __invoke(SetShippingOptionRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        $cart = $this->setShippingOption->handle(
            $this->getOrCreateCart->handle($user),
            $request->string('identifier')->toString(),
        );

        return response()->json([
            'cart_id' => $cart->id,
            'total' => $cart->total->value,
        ]);
    }
}
