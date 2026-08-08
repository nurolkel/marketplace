<?php

namespace App\Actions\Restaurants;

use App\Models\CommissionPromotion;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class AssignRestaurantPromotionAction
{
    /**
     * Assign a commission promotion to a restaurant, starting now.
     * Any previously assigned promotions are replaced so exactly one
     * schedule is active at a time. Platform admins only.
     *
     * @throws AuthorizationException when the actor is not a platform admin
     */
    public function handle(User $actor, Restaurant $restaurant, CommissionPromotion $promotion): Restaurant
    {
        throw_unless($actor->can('manageCommission', $restaurant), AuthorizationException::class);

        $startsAt = now();

        $restaurant->commissionPromotions()->sync([
            $promotion->id => [
                'starts_at' => $startsAt,
                'ends_at' => $promotion->duration_days !== null
                    ? $startsAt->copy()->addDays($promotion->duration_days)
                    : null,
                'orders_used' => 0,
            ],
        ]);

        return $restaurant->refresh();
    }
}
