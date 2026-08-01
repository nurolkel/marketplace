<?php

use App\Actions\Orders\SplitOrderIntoRestaurantOrdersAction;
use App\Enums\RestaurantOrderStatus;
use App\Models\Lunar\Order;
use App\Models\Lunar\Product;
use App\Models\Restaurant;
use Lunar\DataTypes\ShippingOption;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\OrderLine;
use Lunar\Models\ProductVariant;

beforeEach(function () {
    Language::factory()->create(['code' => 'en', 'default' => true]);
    Currency::factory()->create(['default' => true]);

    $this->createVariant = function (?Restaurant $restaurant): ProductVariant {
        $product = Product::factory()->create(['restaurant_id' => $restaurant?->id]);

        return ProductVariant::factory()->create(['product_id' => $product->id]);
    };

    /**
     * Create an order with one line per entry. Each entry needs
     * purchasable_id, sub_total, and total; purchasable_type and type
     * default to a physical product-variant line.
     */
    $this->createOrder = function (array $lines): Order {
        $order = Order::factory()->create();

        foreach ($lines as $line) {
            OrderLine::factory()->create([
                'order_id' => $order->id,
                'purchasable_type' => $line['purchasable_type'] ?? ProductVariant::morphName(),
                'purchasable_id' => $line['purchasable_id'],
                'type' => $line['type'] ?? 'physical',
                'sub_total' => $line['sub_total'],
                'total' => $line['total'],
            ]);
        }

        return $order->refresh();
    };
});

test('a multi-restaurant order splits into one sub-order per restaurant plus a marketplace one', function () {
    $italian = Restaurant::factory()->create();
    $desserts = Restaurant::factory()->create();

    $italianVariantA = ($this->createVariant)($italian);
    $italianVariantB = ($this->createVariant)($italian);
    $dessertVariant = ($this->createVariant)($desserts);
    $marketplaceVariant = ($this->createVariant)(null);

    $order = ($this->createOrder)([
        ['purchasable_id' => $italianVariantA->id, 'sub_total' => 1000, 'total' => 1200],
        ['purchasable_id' => $italianVariantB->id, 'sub_total' => 500, 'total' => 600],
        ['purchasable_id' => $dessertVariant->id, 'sub_total' => 700, 'total' => 840],
        ['purchasable_id' => $marketplaceVariant->id, 'sub_total' => 300, 'total' => 360],
    ]);

    (new SplitOrderIntoRestaurantOrdersAction)->handle($order, fn (Order $passed): Order => $passed);

    $subOrders = $order->refresh()->restaurantOrders;

    expect($subOrders)->toHaveCount(3)
        ->and($subOrders->pluck('reference')->unique())->toHaveCount(3);

    $italianOrder = $subOrders->firstWhere('restaurant_id', $italian->id);
    expect($italianOrder->status)->toBe(RestaurantOrderStatus::Pending)
        ->and($italianOrder->sub_total)->toBe(1500)
        ->and($italianOrder->total)->toBe(1800)
        ->and($italianOrder->reference)->toStartWith("{$order->reference}-R")
        ->and($italianOrder->lines)->toHaveCount(2);

    $dessertOrder = $subOrders->firstWhere('restaurant_id', $desserts->id);
    expect($dessertOrder->total)->toBe(840)
        ->and($dessertOrder->lines)->toHaveCount(1);

    $marketplaceOrder = $subOrders->firstWhere('restaurant_id', null);
    expect($marketplaceOrder->total)->toBe(360)
        ->and($marketplaceOrder->restaurant)->toBeNull()
        ->and($marketplaceOrder->lines)->toHaveCount(1);

    expect($order->lines()->whereNull('restaurant_order_id')->exists())->toBeFalse();
});

test('a single-restaurant order produces exactly one sub-order', function () {
    $restaurant = Restaurant::factory()->create();
    $variant = ($this->createVariant)($restaurant);

    $order = ($this->createOrder)([
        ['purchasable_id' => $variant->id, 'sub_total' => 1000, 'total' => 1000],
    ]);

    (new SplitOrderIntoRestaurantOrdersAction)->handle($order, fn (Order $passed): Order => $passed);

    $subOrders = $order->refresh()->restaurantOrders;

    expect($subOrders)->toHaveCount(1)
        ->and($subOrders->first()->restaurant_id)->toBe($restaurant->id)
        ->and($subOrders->first()->reference)->toBe("{$order->reference}-R1");
});

test('lines without a product-variant purchasable group under the marketplace sub-order', function () {
    $restaurant = Restaurant::factory()->create();
    $variant = ($this->createVariant)($restaurant);

    $order = ($this->createOrder)([
        ['purchasable_id' => $variant->id, 'sub_total' => 1000, 'total' => 1000],
        [
            'purchasable_type' => ShippingOption::class,
            'purchasable_id' => 1,
            'type' => 'shipping',
            'sub_total' => 250,
            'total' => 250,
        ],
    ]);

    (new SplitOrderIntoRestaurantOrdersAction)->handle($order, fn (Order $passed): Order => $passed);

    $subOrders = $order->refresh()->restaurantOrders;

    expect($subOrders)->toHaveCount(2);

    $marketplaceOrder = $subOrders->firstWhere('restaurant_id', null);
    expect($marketplaceOrder->total)->toBe(250)
        ->and($marketplaceOrder->lines()->where('type', 'shipping')->count())->toBe(1);
});

test('re-running the split replaces sub-orders instead of duplicating them', function () {
    $restaurant = Restaurant::factory()->create();
    $variant = ($this->createVariant)($restaurant);

    $order = ($this->createOrder)([
        ['purchasable_id' => $variant->id, 'sub_total' => 1000, 'total' => 1000],
    ]);

    $action = new SplitOrderIntoRestaurantOrdersAction;
    $action->handle($order, fn (Order $passed): Order => $passed);
    $action->handle($order->refresh(), fn (Order $passed): Order => $passed);

    expect($order->refresh()->restaurantOrders)->toHaveCount(1)
        ->and($order->lines()->first()->restaurant_order_id)->toBe($order->restaurantOrders->first()->id);
});

test('the split leaves orders without lines untouched', function () {
    $order = Order::factory()->create();

    (new SplitOrderIntoRestaurantOrdersAction)->handle($order, fn (Order $passed): Order => $passed);

    expect($order->refresh()->restaurantOrders)->toHaveCount(0);
});
