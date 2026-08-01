<?php

namespace App\Policies;

use App\Models\Restaurant;
use App\Models\User;

class RestaurantPolicy
{
    /**
     * Determine whether the user can update the restaurant's profile
     * or settings. Owners and managers only.
     */
    public function update(User $user, Restaurant $restaurant): bool
    {
        return $user->roleInRestaurant($restaurant)?->canManageRestaurant() ?? false;
    }

    /**
     * Determine whether the user can add, remove, or re-role staff
     * members. Owners and managers only.
     */
    public function manageStaff(User $user, Restaurant $restaurant): bool
    {
        return $user->roleInRestaurant($restaurant)?->canManageStaff() ?? false;
    }

    /**
     * Determine whether the user can see the staff list. Anyone who
     * works at the restaurant, in any role.
     */
    public function viewAnyStaff(User $user, Restaurant $restaurant): bool
    {
        return $user->isStaffOf($restaurant);
    }

    /**
     * Determine whether the user can attach or detach categories on
     * the restaurant. Owners and managers only, same rule as update.
     */
    public function manageCategories(User $user, Restaurant $restaurant): bool
    {
        return $user->roleInRestaurant($restaurant)?->canManageRestaurant() ?? false;
    }
}
