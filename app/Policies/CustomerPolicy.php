<?php

namespace App\Policies;

use App\Models\Lunar\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Determine whether the user can manage the customer record,
     * including its address book. Only the linked account owner;
     * platform admins pass through the Gate::before hook.
     */
    public function manageAddresses(User $user, Customer $customer): bool
    {
        return $customer->users()->where('users.id', $user->id)->exists();
    }
}
