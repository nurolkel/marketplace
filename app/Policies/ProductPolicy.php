<?php

namespace App\Policies;

use App\Models\Lunar\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Determine whether the user can update the product, including its
     * prices and content. Any staff member of the owning restaurant.
     */
    public function update(User $user, Product $product): bool
    {
        return $product->restaurant !== null
            && $user->isStaffOf($product->restaurant);
    }

    /**
     * Determine whether the user can delete the product. Any staff
     * member of the owning restaurant.
     */
    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }
}
