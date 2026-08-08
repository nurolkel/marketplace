<?php

namespace App\Models;

use App\Enums\RestaurantOrderStatus;
use App\Models\Lunar\Order;
use Database\Factories\RestaurantOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Lunar\Models\OrderLine;

/**
 * A restaurant's share of a customer order. One Lunar order splits into
 * one sub-order per restaurant (plus a null-restaurant sub-order for
 * marketplace-owned items), each fulfilled independently.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $restaurant_id
 * @property string $reference
 * @property RestaurantOrderStatus $status
 * @property int $sub_total
 * @property int $total
 * @property string|null $paused_from_status
 * @property string|null $pause_reason
 * @property Carbon|null $paused_at
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by_id
 * @property string|null $cancellation_reason
 * @property Carbon|null $placed_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $preparing_at
 * @property Carbon|null $dispatched_at
 * @property Carbon|null $completed_at
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order $order
 * @property-read Restaurant|null $restaurant
 * @property-read User|null $cancelledBy
 * @property-read Collection<int, OrderLine> $lines
 * @property-read Review|null $review
 */
#[Fillable([
    'order_id',
    'restaurant_id',
    'reference',
    'status',
    'sub_total',
    'total',
    'paused_from_status',
    'pause_reason',
    'paused_at',
    'cancelled_at',
    'cancelled_by_id',
    'cancellation_reason',
    'placed_at',
    'accepted_at',
    'preparing_at',
    'dispatched_at',
    'completed_at',
    'meta',
])]
class RestaurantOrder extends Model
{
    /** @use HasFactory<RestaurantOrderFactory> */
    use HasFactory;

    /**
     * Mirror the database default so new instances are pending.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RestaurantOrderStatus::class,
            'sub_total' => 'integer',
            'total' => 'integer',
            'paused_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'placed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'preparing_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'completed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * The parent customer-facing Lunar order.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * The restaurant fulfilling this sub-order. Null means the
     * marketplace itself owns the items and admins fulfil them.
     *
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * The Lunar order lines grouped into this sub-order.
     *
     * @return HasMany<OrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class, 'restaurant_order_id');
    }

    /**
     * The user who cancelled the sub-order, if cancelled.
     *
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_id');
    }

    /**
     * The customer's review of this sub-order, if they left one.
     *
     * @return MorphOne<Review, $this>
     */
    public function review(): MorphOne
    {
        return $this->morphOne(Review::class, 'reviewable');
    }

    /**
     * Whether the sub-order may move to the given status.
     */
    public function canTransitionTo(RestaurantOrderStatus $to): bool
    {
        return $this->status->canTransitionTo($to);
    }

    /**
     * Whether the sub-order has reached a terminal status.
     */
    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }
}
