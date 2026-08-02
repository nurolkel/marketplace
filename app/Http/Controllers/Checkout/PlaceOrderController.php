<?php

namespace App\Http\Controllers\Checkout;

use App\Actions\Checkout\GetOrCreateCartAction;
use App\Actions\Checkout\PlaceOrderAction;
use App\Exceptions\PaymentAuthorizationFailedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\PlaceOrderRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class PlaceOrderController extends Controller
{
    public function __construct(
        private GetOrCreateCartAction $getOrCreateCart,
        private PlaceOrderAction $placeOrder,
    ) {}

    public function __invoke(PlaceOrderRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        try {
            $order = $this->placeOrder->handle(
                $this->getOrCreateCart->handle($user),
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
}
