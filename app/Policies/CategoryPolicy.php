<?php

namespace App\Policies;

use App\Models\Lunar\Collection;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Determine whether the user can update the category. Any staff
     * member of the owning restaurant. Marketplace-wide categories
     * (no restaurant) are only reachable by platform admins through
     * the Gate::before hook.
     */
    public function update(User $user, Collection $collection): bool
    {
        return $collection->restaurant !== null
            && $user->isStaffOf($collection->restaurant);
    }

    /**
     * Determine whether the user can delete the category.
     */
    public function delete(User $user, Collection $collection): bool
    {
        return $this->update($user, $collection);
    }
}
