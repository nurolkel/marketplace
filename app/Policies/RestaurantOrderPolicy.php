<?php

namespace App\Policies;

use App\Models\RestaurantOrder;
use App\Models\User;

class RestaurantOrderPolicy
{
    /**
     * The customer who placed the parent order, any staff of the
     * fulfilling restaurant, and admins (via Gate::before) may view.
     */
    public function view(User $user, RestaurantOrder $restaurantOrder): bool
    {
        return $this->isCustomerOfParentOrder($user, $restaurantOrder)
            || $this->canManageOrders($user, $restaurantOrder);
    }

    /**
     * Staff of the fulfilling restaurant may move the sub-order
     * through its lifecycle.
     */
    public function transition(User $user, RestaurantOrder $restaurantOrder): bool
    {
        return $this->canManageOrders($user, $restaurantOrder);
    }

    public function pause(User $user, RestaurantOrder $restaurantOrder): bool
    {
        return $this->canManageOrders($user, $restaurantOrder);
    }

    public function resume(User $user, RestaurantOrder $restaurantOrder): bool
    {
        return $this->canManageOrders($user, $restaurantOrder);
    }

    /**
     * Customers may cancel within their window (until the restaurant
     * starts preparing); staff may cancel any sub-order the lifecycle
     * still allows.
     */
    public function cancel(User $user, RestaurantOrder $restaurantOrder): bool
    {
        if ($this->isCustomerOfParentOrder($user, $restaurantOrder)) {
            return $restaurantOrder->status->canBeCancelledByCustomer();
        }

        return $this->canManageOrders($user, $restaurantOrder);
    }

    /**
     * Refunds are restricted to designated restaurant staff (and
     * admins via Gate::before).
     */
    public function refund(User $user, RestaurantOrder $restaurantOrder): bool
    {
        return $this->canManageOrders($user, $restaurantOrder);
    }

    /**
     * Whether the user placed the sub-order's parent order.
     */
    private function isCustomerOfParentOrder(User $user, RestaurantOrder $restaurantOrder): bool
    {
        return $restaurantOrder->order->user_id === $user->id;
    }

    /**
     * Whether the user works at the fulfilling restaurant in a role
     * allowed to manage orders. Marketplace-owned sub-orders (null
     * restaurant) have no staff and are admin-only.
     */
    private function canManageOrders(User $user, RestaurantOrder $restaurantOrder): bool
    {
        $restaurant = $restaurantOrder->restaurant;

        return $restaurant !== null
            && ($user->roleInRestaurant($restaurant)?->canManageOrders() ?? false);
    }
}
