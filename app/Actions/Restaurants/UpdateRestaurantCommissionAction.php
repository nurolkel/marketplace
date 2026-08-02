<?php

namespace App\Actions\Restaurants;

use App\Models\CommissionTier;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class UpdateRestaurantCommissionAction
{
    /**
     * Set the restaurant's commission tier and/or custom rate override
     * (in basis points, max 10000 = 100%). Passing null clears the
     * value. Platform admins only — restaurants never set their own
     * rates.
     *
     * @param  int<0, max>|null  $commissionTierId
     * @param  int<0, max>|null  $commissionRate
     *
     * @throws AuthorizationException when the actor is not a platform admin
     * @throws ValidationException when the rate is out of range
     */
    public function handle(User $actor, Restaurant $restaurant, ?int $commissionTierId = null, ?int $commissionRate = null): Restaurant
    {
        throw_unless($actor->can('manageCommission', $restaurant), AuthorizationException::class);

        throw_if(
            $commissionRate !== null && $commissionRate > 10000,
            ValidationException::withMessages([
                'commission_rate' => 'The rate must be between 0 and 10000 basis points.',
            ]),
        );

        if ($commissionTierId !== null) {
            CommissionTier::findOrFail($commissionTierId);
        }

        $restaurant->commission_tier_id = $commissionTierId;
        $restaurant->commission_rate = $commissionRate;
        $restaurant->save();

        return $restaurant->refresh();
    }
}
