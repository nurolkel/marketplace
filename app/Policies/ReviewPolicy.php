<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * Only the author may delete their review. Admins already pass
     * every ability through the Gate::before hook, which doubles as
     * the moderation escape hatch.
     */
    public function delete(User $user, Review $review): bool
    {
        return $review->user_id === $user->id;
    }
}
