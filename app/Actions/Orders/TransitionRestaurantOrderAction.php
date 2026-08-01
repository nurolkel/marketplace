<?php

namespace App\Actions\Orders;

use App\Enums\RestaurantOrderStatus;
use App\Events\RestaurantOrderStatusChanged;
use App\Exceptions\InvalidRestaurantOrderTransitionException;
use App\Models\RestaurantOrder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class TransitionRestaurantOrderAction
{
    /**
     * Move a sub-order forward through its fulfilment lifecycle,
     * stamping the status's timestamp column on first entry.
     *
     * @throws AuthorizationException when the actor may not manage the sub-order
     * @throws InvalidRestaurantOrderTransitionException when the transition is not allowed
     */
    public function handle(User $actor, RestaurantOrder $restaurantOrder, RestaurantOrderStatus $to): RestaurantOrder
    {
        throw_unless($actor->can('transition', $restaurantOrder), AuthorizationException::class);

        $from = $restaurantOrder->status;

        throw_unless(
            $from->canTransitionTo($to),
            InvalidRestaurantOrderTransitionException::fromTo($from, $to),
        );

        $attributes = ['status' => $to];

        if (($column = $to->timestampColumn()) !== null && $restaurantOrder->{$column} === null) {
            $attributes[$column] = now();
        }

        $restaurantOrder->update($attributes);

        RestaurantOrderStatusChanged::dispatch($restaurantOrder, $actor, $from, $to);

        return $restaurantOrder->refresh();
    }
}
