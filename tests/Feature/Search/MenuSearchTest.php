<?php

use App\Actions\Search\SearchRestaurantProductsAction;
use App\Models\Lunar\Collection;
use App\Models\Lunar\Product;
use App\Models\Restaurant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lunar\FieldTypes\Text;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Price;
use Lunar\Models\ProductVariant;

beforeEach(function () {
    Language::factory()->create(['code' => 'en', 'default' => true]);
    Currency::factory()->create(['default' => true]);
});

/**
 * Create a published menu item for the restaurant, optionally with a
 * priced variant (price in cents).
 */
function createMenuItem(Restaurant $restaurant, string $name, ?int $price = null): Product
{
    $product = Product::factory()->create([
        'restaurant_id' => $restaurant->id,
        'attribute_data' => collect(['name' => new Text($name)]),
    ]);

    if ($price !== null) {
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        Price::factory()->create([
            'priceable_type' => 'product_variant',
            'priceable_id' => $variant->id,
            'price' => $price,
        ]);
    }

    return $product;
}

/**
 * Display names of the paginator's current page, in order.
 *
 * @return array<int, string|null>
 */
function pageNames(LengthAwarePaginator $results): array
{
    return $results->getCollection()->map(fn (Product $product): ?string => $product->displayName())->all();
}

test('search finds menu items by name within the restaurant only', function () {
    $napoli = Restaurant::factory()->active()->create();
    $other = Restaurant::factory()->active()->create();

    createMenuItem($napoli, 'Lobster Ravioli');
    createMenuItem($other, 'Lobster Roll');

    $results = (new SearchRestaurantProductsAction)->handle($napoli, 'lobster');

    expect($results->total())->toBe(1)
        ->and(pageNames($results))->toBe(['Lobster Ravioli']);
});

test('a blank query browses the published menu and hides drafts', function () {
    $restaurant = Restaurant::factory()->active()->create();
    $other = Restaurant::factory()->active()->create();

    createMenuItem($restaurant, 'Margherita Pizza');
    createMenuItem($restaurant, 'Tiramisu');
    createMenuItem($other, 'Other Restaurant Dish');
    Product::factory()->create([
        'restaurant_id' => $restaurant->id,
        'status' => 'draft',
        'attribute_data' => collect(['name' => new Text('Unreleased Special')]),
    ]);

    $results = (new SearchRestaurantProductsAction)->handle($restaurant, null);

    expect($results->total())->toBe(2)
        ->and(pageNames($results))->toContain('Margherita Pizza', 'Tiramisu')
        ->not->toContain('Unreleased Special', 'Other Restaurant Dish');
});

test('menu items filter by Lunar collection menu section', function () {
    $restaurant = Restaurant::factory()->active()->create();

    $mains = Collection::factory()->create([
        'restaurant_id' => $restaurant->id,
        'attribute_data' => collect(['name' => new Text('Mains')]),
    ]);
    $desserts = Collection::factory()->create([
        'restaurant_id' => $restaurant->id,
        'attribute_data' => collect(['name' => new Text('Desserts')]),
    ]);

    $pizza = createMenuItem($restaurant, 'Margherita Pizza');
    $pizza->collections()->attach($mains->id);
    $tiramisu = createMenuItem($restaurant, 'Tiramisu');
    $tiramisu->collections()->attach($desserts->id);

    $results = (new SearchRestaurantProductsAction)->handle($restaurant, null, $mains->id);

    expect($results->total())->toBe(1)
        ->and(pageNames($results))->toBe(['Margherita Pizza']);
});

test('menu items sort by minimum variant price', function () {
    $restaurant = Restaurant::factory()->active()->create();

    createMenuItem($restaurant, 'Expensive Dish', 3000);
    createMenuItem($restaurant, 'Cheap Dish', 500);
    createMenuItem($restaurant, 'Mid Dish', 1200);

    $action = new SearchRestaurantProductsAction;
    $ascending = $action->handle($restaurant, null, null, 'price_asc');
    $descending = $action->handle($restaurant, null, null, 'price_desc');

    expect(pageNames($ascending))->toBe(['Cheap Dish', 'Mid Dish', 'Expensive Dish'])
        ->and(pageNames($descending))->toBe(['Expensive Dish', 'Mid Dish', 'Cheap Dish']);
});

test('price sorting uses the cheapest variant when a product has several', function () {
    $restaurant = Restaurant::factory()->active()->create();

    $single = createMenuItem($restaurant, 'Single Price Dish', 900);
    $multi = createMenuItem($restaurant, 'Multi Variant Dish');
    $large = ProductVariant::factory()->create(['product_id' => $multi->id]);
    Price::factory()->create(['priceable_type' => 'product_variant', 'priceable_id' => $large->id, 'price' => 2000]);
    $small = ProductVariant::factory()->create(['product_id' => $multi->id]);
    Price::factory()->create(['priceable_type' => 'product_variant', 'priceable_id' => $small->id, 'price' => 400]);

    $results = (new SearchRestaurantProductsAction)->handle($restaurant, null, null, 'price_asc');

    expect(pageNames($results))->toBe(['Multi Variant Dish', 'Single Price Dish']);
});

test('menu items sort by name', function () {
    $restaurant = Restaurant::factory()->active()->create();

    createMenuItem($restaurant, 'Zucchini Fries');
    createMenuItem($restaurant, 'Apple Pie');
    createMenuItem($restaurant, 'Minestrone');

    $results = (new SearchRestaurantProductsAction)->handle($restaurant, null, null, 'name');

    expect(pageNames($results))->toBe(['Apple Pie', 'Minestrone', 'Zucchini Fries']);
});

test('menu items sort by newest first', function () {
    $restaurant = Restaurant::factory()->active()->create();

    $old = createMenuItem($restaurant, 'Old Dish');
    $old->created_at = now()->subDays(3);
    $old->save();
    $new = createMenuItem($restaurant, 'New Dish');
    $new->created_at = now()->subDay();
    $new->save();

    $results = (new SearchRestaurantProductsAction)->handle($restaurant, null, null, 'newest');

    expect(pageNames($results))->toBe(['New Dish', 'Old Dish']);
});

test('menu results are paginated', function () {
    $restaurant = Restaurant::factory()->active()->create();

    foreach (range(1, 7) as $number) {
        createMenuItem($restaurant, "Dish {$number}");
    }

    $results = (new SearchRestaurantProductsAction)->handle($restaurant, null, null, 'newest', 3);

    expect($results)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($results->count())->toBe(3)
        ->and($results->total())->toBe(7)
        ->and($results->lastPage())->toBe(3);
});

test('an unknown sort falls back to relevance without leaking into the query', function () {
    $restaurant = Restaurant::factory()->active()->create();
    createMenuItem($restaurant, 'Margherita Pizza');

    $results = (new SearchRestaurantProductsAction)->handle($restaurant, 'pizza', null, "')) OR 1=1 --");

    expect($results->total())->toBe(1)
        ->and(Product::count())->toBe(1);
});
