<?php

namespace App\Http\Controllers\Checkout;

use App\Actions\Checkout\AddCartLineAction;
use App\Actions\Checkout\GetOrCreateCartAction;
use App\Actions\Checkout\RemoveCartLineAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\StoreCartLineRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartLineController extends Controller
{
    public function __construct(
        private GetOrCreateCartAction $getOrCreateCart,
        private AddCartLineAction $addCartLine,
        private RemoveCartLineAction $removeCartLine,
    ) {}

    public function store(StoreCartLineRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        $cart = $this->addCartLine->handle(
            $this->getOrCreateCart->handle($user),
            $request->integer('purchasable_id'),
            $request->integer('quantity'),
        );

        return response()->json([
            'cart_id' => $cart->id,
            'total' => $cart->total->value,
            'lines' => $cart->lines->count(),
        ], 201);
    }

    public function destroy(Request $request, int $line): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        $cart = $this->removeCartLine->handle(
            $this->getOrCreateCart->handle($user),
            $line,
        );

        return response()->json([
            'cart_id' => $cart->id,
            'total' => $cart->total->value,
            'lines' => $cart->lines->count(),
        ]);
    }
}
