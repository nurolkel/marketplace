<?php

namespace App\Actions\Orders;

use App\Enums\RestaurantOrderStatus;
use App\Models\Lunar\Order;
use App\Models\Lunar\Product;
use App\Models\RestaurantOrder;
use Closure;
use Illuminate\Support\Collection;
use Lunar\Models\Contracts\Order as OrderContract;
use Lunar\Models\OrderLine;
use Lunar\Models\ProductVariant;

class SplitOrderIntoRestaurantOrdersAction
{
    /**
     * Pipeline stage appended to Lunar's order creation pipeline
     * (config/lunar/orders.php). Groups the order's lines by the
     * restaurant owning each product and creates one sub-order per
     * restaurant. Lines whose purchasable is not a product variant
     * (e.g. shipping) or whose product is marketplace-owned group
     * under a null-restaurant sub-order fulfilled by platform admins.
     * Re-running the split replaces any existing sub-orders, keeping
     * Lunar's draft-order update flow idempotent.
     *
     * @param  Closure(OrderContract): mixed  $next
     */
    public function handle(OrderContract $order, Closure $next): mixed
    {
        if (! $order instanceof Order || ! $order->exists) {
            return $next($order);
        }

        $this->split($order);

        return $next($order->refresh());
    }

    /**
     * Replace the order's sub-orders with a fresh per-restaurant split.
     */
    private function split(Order $order): void
    {
        $order->restaurantOrders()->delete();

        $variants = $this->variantsByLine($order);

        /** @var \Illuminate\Database\Eloquent\Collection<int, OrderLine> $lines */
        $lines = $order->lines;

        $groups = $lines->groupBy(
            fn (OrderLine $line): string => (string) ($this->restaurantIdFor($line, $variants) ?? 'marketplace')
        );

        $index = 0;

        foreach ($groups as $key => $groupedLines) {
            $index++;

            $subOrder = RestaurantOrder::create([
                'order_id' => $order->id,
                'restaurant_id' => $key === 'marketplace' ? null : (int) $key,
                'reference' => "{$order->reference}-R{$index}",
                'status' => RestaurantOrderStatus::Pending,
                'sub_total' => $groupedLines->sum(fn (OrderLine $line): int => (int) $line->getRawOriginal('sub_total')),
                'total' => $groupedLines->sum(fn (OrderLine $line): int => (int) $line->getRawOriginal('total')),
                'placed_at' => $order->placed_at,
            ]);

            OrderLine::query()
                ->whereIn('id', $groupedLines->modelKeys())
                ->update(['restaurant_order_id' => $subOrder->id]);
        }
    }

    /**
     * Eager-load the purchasable variants for all variant-backed lines
     * in one query, keyed by variant id. Non-variant purchasables are
     * skipped because their morph target may not be an Eloquent model.
     *
     * @return Collection<int, ProductVariant>
     */
    private function variantsByLine(Order $order): Collection
    {
        $variantIds = $order->lines
            ->where('purchasable_type', ProductVariant::morphName())
            ->pluck('purchasable_id');

        if ($variantIds->isEmpty()) {
            return collect();
        }

        return ProductVariant::query()
            ->with('product')
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');
    }

    /**
     * The restaurant owning the line's product, or null when the line
     * is marketplace-owned or not backed by a product variant.
     *
     * @param  Collection<int, ProductVariant>  $variants
     */
    private function restaurantIdFor(OrderLine $line, Collection $variants): ?int
    {
        if ($line->purchasable_type !== ProductVariant::morphName()) {
            return null;
        }

        $product = $variants->get($line->purchasable_id)?->product;

        return $product instanceof Product ? $product->restaurant_id : null;
    }
}
