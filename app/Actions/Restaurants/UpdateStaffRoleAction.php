<?php

namespace App\Actions\Restaurants;

use App\Enums\RestaurantRole;
use App\Exceptions\CannotRemoveLastOwnerException;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class UpdateStaffRoleAction
{
    /**
     * Promote or demote an existing staff member.
     *
     * @throws AuthorizationException when the actor may not manage staff
     * @throws CannotRemoveLastOwnerException when demoting would leave the restaurant without an owner
     */
    public function handle(User $actor, Restaurant $restaurant, User $member, RestaurantRole $role): void
    {
        throw_unless(
            $actor->can('manageStaff', $restaurant),
            AuthorizationException::class,
        );

        throw_if(
            $this->isSoleOwnerBeingDemoted($restaurant, $member, $role),
            CannotRemoveLastOwnerException::forRestaurant(),
        );

        $restaurant->members()->updateExistingPivot($member->id, [
            'role' => $role->value,
        ]);
    }

    private function isSoleOwnerBeingDemoted(Restaurant $restaurant, User $member, RestaurantRole $newRole): bool
    {
        return $newRole !== RestaurantRole::Owner
            && $member->hasRoleInRestaurant($restaurant, RestaurantRole::Owner)
            && $restaurant->owners()->count() === 1;
    }
}
