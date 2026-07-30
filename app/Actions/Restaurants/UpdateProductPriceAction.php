<?php

namespace App\Actions\Restaurants;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Lunar\Models\Currency;
use Lunar\Models\Price;
use Lunar\Models\ProductVariant;

class UpdateProductPriceAction
{
    /**
     * Set the base price (min_quantity tier of 1) for a variant in the
     * default currency. Tier pricing for bulk quantities is deferred.
     *
     * @param  int  $price  Price in the currency's smallest unit (e.g. cents)
     * @param  int|null  $comparePrice  Optional "was" price, same unit
     *
     * @throws AuthorizationException when the actor is not staff of the owning restaurant
     */
    public function handle(User $actor, ProductVariant $variant, int $price, ?int $comparePrice = null): Price
    {
        $product = $variant->product;

        throw_unless(
            $product !== null && $actor->can('update', $product),
            AuthorizationException::class,
        );

        /** @var Price $priceModel */
        $priceModel = $variant->prices()->updateOrCreate(
            [
                'currency_id' => $this->defaultCurrency()->id,
                'customer_group_id' => null,
                'min_quantity' => 1,
            ],
            [
                'price' => $price,
                'compare_price' => $comparePrice,
            ],
        );

        return $priceModel;
    }

    private function defaultCurrency(): Currency
    {
        return Currency::where('default', true)->firstOrFail();
    }
}
