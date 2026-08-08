<?php

namespace App\Models;

use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A customer's review of a restaurant, a fulfilled sub-order, or the
 * marketplace itself (a null reviewable). Each user gets one review
 * per subject: re-posting updates the existing row instead of
 * creating a duplicate.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $reviewable_type
 * @property int|null $reviewable_id
 * @property int $rating
 * @property string|null $title
 * @property string|null $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $reviewable
 * @property-read User $user
 */
#[Fillable(['user_id', 'reviewable_type', 'reviewable_id', 'rating', 'title', 'body'])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    /**
     * The subject of the review: a restaurant, a sub-order, or null
     * when the review is about the platform itself.
     *
     * @return MorphTo<Model, $this>
     */
    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The customer who wrote the review.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
