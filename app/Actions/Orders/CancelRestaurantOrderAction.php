<?php

namespace App\Actions\Orders;

use App\Enums\RestaurantOrderStatus;
use App\Events\RestaurantOrderStatusChanged;
use App\Exceptions\InvalidRestaurantOrderTransitionException;
use App\Models\RestaurantOrder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class CancelRestaurantOrderAction
{
    public function __construct(
        private RefundRestaurantOrderAction $refunds,
    ) {}

    /**
     * Cancel a sub-order, recording who cancelled it and why. Customers
     * may only cancel within their window (enforced by the policy and
     * the enum); staff may cancel any non-dispatched, non-terminal
     * sub-order. When $refund is true and payment was captured, a full
     * refund is issued first — refunding stays restricted to staff.
     *
     * @throws AuthorizationException when the actor may not cancel (or refund, when requested)
     * @throws InvalidRestaurantOrderTransitionException when the sub-order can no longer be cancelled
     */
    public function handle(User $actor, RestaurantOrder $restaurantOrder, ?string $reason = null, bool $refund = false): RestaurantOrder
    {
        throw_unless($actor->can('cancel', $restaurantOrder), AuthorizationException::class);

        $from = $restaurantOrder->status;

        throw_unless(
            $from->canTransitionTo(RestaurantOrderStatus::Cancelled),
            InvalidRestaurantOrderTransitionException::fromTo($from, RestaurantOrderStatus::Cancelled),
        );

        if ($refund && $from->isRefundable()) {
            $this->refunds->handle($actor, $restaurantOrder->refresh());
        }

        $restaurantOrder->update([
            'status' => RestaurantOrderStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by_id' => $actor->id,
            'cancellation_reason' => $reason,
        ]);

        RestaurantOrderStatusChanged::dispatch($restaurantOrder, $actor, $from, RestaurantOrderStatus::Cancelled);

        return $restaurantOrder->refresh();
    }
}
