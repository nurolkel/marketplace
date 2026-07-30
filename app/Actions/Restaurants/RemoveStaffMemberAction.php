<?php

namespace App\Actions\Restaurants;

use App\Enums\RestaurantRole;
use App\Exceptions\CannotRemoveLastOwnerException;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class RemoveStaffMemberAction
{
    /**
     * Remove a user from the restaurant's staff.
     *
     * @throws AuthorizationException when the actor may not manage staff
     * @throws CannotRemoveLastOwnerException when removing would leave the restaurant without an owner
     */
    public function handle(User $actor, Restaurant $restaurant, User $member): void
    {
        throw_unless(
            $actor->can('manageStaff', $restaurant),
            AuthorizationException::class,
        );

        throw_if(
            $this->isSoleOwner($restaurant, $member),
            CannotRemoveLastOwnerException::forRestaurant(),
        );

        $restaurant->members()->detach($member);
    }

    private function isSoleOwner(Restaurant $restaurant, User $member): bool
    {
        return $member->hasRoleInRestaurant($restaurant, RestaurantRole::Owner)
            && $restaurant->owners()->count() === 1;
    }
}
