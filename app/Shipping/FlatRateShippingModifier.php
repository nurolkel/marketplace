<?php

namespace App\Shipping;

use Closure;
use Lunar\Base\ShippingModifier;
use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Contracts\Cart;
use Lunar\Models\Currency;
use Lunar\Models\TaxClass;

class FlatRateShippingModifier extends ShippingModifier
{
    /**
     * Offer a single flat "Standard delivery" option at no charge
     * while delivery zones and per-restaurant rates are designed.
     * Required so shippable carts pass order-creation validation.
     */
    public function handle(Cart $cart, Closure $next)
    {
        ShippingManifest::addOption(new ShippingOption(
            name: 'Standard delivery',
            description: 'Flat-rate delivery while full shipping options are built.',
            identifier: 'standard',
            price: new Price(0, Currency::getDefault(), 1),
            taxClass: TaxClass::query()->firstOrCreate(['name' => 'Standard delivery']),
            collect: false,
        ));

        $next($cart);
    }
}
