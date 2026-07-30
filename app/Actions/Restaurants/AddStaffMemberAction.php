<?php

namespace App\Actions\Restaurants;

use App\Enums\RestaurantRole;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class AddStaffMemberAction
{
    /**
     * Add a user to the restaurant's staff, or update their role if
     * they are already a member.
     *
     * @throws AuthorizationException when the actor may not manage staff
     */
    public function handle(User $actor, Restaurant $restaurant, User $member, RestaurantRole $role): void
    {
        throw_unless(
            $actor->can('manageStaff', $restaurant),
            AuthorizationException::class,
        );

        $restaurant->members()->syncWithoutDetaching([
            $member->id => ['role' => $role->value],
        ]);
    }
}
