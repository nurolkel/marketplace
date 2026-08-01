<?php

namespace App\Actions\Orders;

use App\Enums\RestaurantOrderStatus;
use App\Events\RestaurantOrderStatusChanged;
use App\Exceptions\InvalidRestaurantOrderTransitionException;
use App\Models\RestaurantOrder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

class PauseRestaurantOrderAction
{
    /**
     * Put an actively worked sub-order on hold, remembering the status
     * it resumes to and the reason shown to the customer.
     *
     * @throws AuthorizationException when the actor may not manage the sub-order
     * @throws InvalidRestaurantOrderTransitionException when the sub-order cannot be paused
     * @throws InvalidArgumentException when no pause reason is given
     */
    public function handle(User $actor, RestaurantOrder $restaurantOrder, string $reason): RestaurantOrder
    {
        throw_unless($actor->can('pause', $restaurantOrder), AuthorizationException::class);

        throw_if(trim($reason) === '', InvalidArgumentException::class, 'A pause reason is required.');

        $from = $restaurantOrder->status;

        throw_unless(
            $from->canBePaused(),
            InvalidRestaurantOrderTransitionException::fromTo($from, RestaurantOrderStatus::OnHold),
        );

        $restaurantOrder->update([
            'status' => RestaurantOrderStatus::OnHold,
            'paused_from_status' => $from->value,
            'pause_reason' => $reason,
            'paused_at' => now(),
        ]);

        RestaurantOrderStatusChanged::dispatch($restaurantOrder, $actor, $from, RestaurantOrderStatus::OnHold);

        return $restaurantOrder->refresh();
    }
}
