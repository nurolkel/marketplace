<?php

namespace App\Actions\Orders;

use App\Enums\RestaurantOrderStatus;
use App\Events\RestaurantOrderStatusChanged;
use App\Exceptions\InvalidRestaurantOrderTransitionException;
use App\Models\RestaurantOrder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class ResumeRestaurantOrderAction
{
    /**
     * Resume an on-hold sub-order back to the status it was paused
     * from, clearing the pause fields.
     *
     * @throws AuthorizationException when the actor may not manage the sub-order
     * @throws InvalidRestaurantOrderTransitionException when the sub-order is not on hold
     */
    public function handle(User $actor, RestaurantOrder $restaurantOrder): RestaurantOrder
    {
        throw_unless($actor->can('resume', $restaurantOrder), AuthorizationException::class);

        $from = $restaurantOrder->status;

        throw_unless(
            $from === RestaurantOrderStatus::OnHold && $restaurantOrder->paused_from_status !== null,
            InvalidRestaurantOrderTransitionException::notResumable($from),
        );

        /** @var string $pausedFrom */
        $pausedFrom = $restaurantOrder->paused_from_status;
        $to = RestaurantOrderStatus::from($pausedFrom);

        $restaurantOrder->update([
            'status' => $to,
            'paused_from_status' => null,
            'pause_reason' => null,
            'paused_at' => null,
        ]);

        RestaurantOrderStatusChanged::dispatch($restaurantOrder, $actor, $from, $to);

        return $restaurantOrder->refresh();
    }
}
