<?php

namespace App\Http\Controllers\Checkout;

use App\Actions\Checkout\CreateCheckoutSessionAction;
use App\Actions\Checkout\GetOrCreateCartAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $url = $this->createCheckoutSession->handle(
            $this->getOrCreateCart->handle($user)
        );

        return response()->json(['url' => $url], 201);
    }
}
