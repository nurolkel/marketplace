<?php

namespace App\Http\Controllers\Reviews;

use App\Actions\Reviews\StoreReviewAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reviews\StoreReviewRequest;
use App\Models\RestaurantOrder;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class RestaurantOrderReviewController extends Controller
{
    public function __construct(private StoreReviewAction $storeReview) {}

    public function store(StoreReviewRequest $request, RestaurantOrder $restaurantOrder): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var string|null $title */
        $title = $request->validated('title');

        /** @var string|null $body */
        $body = $request->validated('body');

        $review = $this->storeReview->handle(
            $user,
            $restaurantOrder,
            $request->integer('rating'),
            $title,
            $body,
        );

        return response()->json([
            'id' => $review->id,
            'rating' => $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'author' => $user->name,
            'created_at' => $review->created_at?->toISOString(),
        ], 201);
    }
}
