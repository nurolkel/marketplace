<?php

namespace App\Http\Controllers\Reviews;

use App\Actions\Reviews\StoreReviewAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reviews\StoreReviewRequest;
use App\Models\Restaurant;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class RestaurantReviewController extends Controller
{
    public function __construct(private StoreReviewAction $storeReview) {}

    public function index(Restaurant $restaurant): JsonResponse
    {
        $paginator = $restaurant->reviews()
            ->with('user:id,name')
            ->latest()
            ->paginate(10);

        return response()->json([
            'average_rating' => $restaurant->averageRating(),
            'reviews_count' => $paginator->total(),
            'reviews' => collect($paginator->items())->map(fn (Review $review): array => [
                'id' => $review->id,
                'rating' => $review->rating,
                'title' => $review->title,
                'body' => $review->body,
                'author' => $review->user->name,
                'created_at' => $review->created_at?->toISOString(),
            ])->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreReviewRequest $request, Restaurant $restaurant): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var string|null $title */
        $title = $request->validated('title');

        /** @var string|null $body */
        $body = $request->validated('body');

        $review = $this->storeReview->handle(
            $user,
            $restaurant,
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
