<?php

namespace App\Actions\Reviews;

use App\Enums\RestaurantOrderStatus;
use App\Models\Restaurant;
use App\Models\RestaurantOrder;
use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class StoreReviewAction
{
    /**
     * Create or refresh the user's review of a restaurant, a fulfilled
     * sub-order, or the marketplace platform itself (null reviewable).
     * Each user gets one review per subject, so re-posting updates the
     * existing row — enforced here rather than by the unique index,
     * which cannot dedupe the null reviewable columns of platform
     * reviews.
     *
     * @throws ValidationException when the rating falls outside 1-5
     * @throws AuthorizationException when the user may not review the subject
     */
    public function handle(User $user, Restaurant|RestaurantOrder|null $reviewable, int $rating, ?string $title, ?string $body): Review
    {
        if ($rating < 1 || $rating > 5) {
            throw ValidationException::withMessages([
                'rating' => 'The rating must be between 1 and 5.',
            ]);
        }

        $this->ensureEligible($user, $reviewable);

        return Review::updateOrCreate(
            [
                'user_id' => $user->id,
                'reviewable_type' => $reviewable?->getMorphClass(),
                'reviewable_id' => $reviewable?->getKey(),
            ],
            [
                'rating' => $rating,
                'title' => $title,
                'body' => $body,
            ],
        );
    }

    /**
     * Platform reviews are open to every account holder; restaurant
     * and sub-order reviews demand a completed purchase.
     *
     * @throws AuthorizationException
     */
    private function ensureEligible(User $user, Restaurant|RestaurantOrder|null $reviewable): void
    {
        if ($reviewable === null) {
            return;
        }

        if ($reviewable instanceof RestaurantOrder) {
            throw_unless(
                $reviewable->status === RestaurantOrderStatus::Completed
                    && $reviewable->order->user_id === $user->id,
                AuthorizationException::class,
            );

            return;
        }

        throw_unless($this->hasCompletedSubOrderWith($user, $reviewable), AuthorizationException::class);
    }

    /**
     * Whether the user has at least one completed sub-order fulfilled
     * by the restaurant, which entitles them to review it.
     */
    private function hasCompletedSubOrderWith(User $user, Restaurant $restaurant): bool
    {
        return $restaurant->restaurantOrders()
            ->where('status', RestaurantOrderStatus::Completed->value)
            ->whereHas('order', fn (Builder $query) => $query->where('user_id', $user->id))
            ->exists();
    }
}
