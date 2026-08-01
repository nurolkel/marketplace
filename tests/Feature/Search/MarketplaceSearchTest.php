<?php

use App\Actions\Search\SearchMarketplaceAction;
use App\Models\Lunar\Collection;
use App\Models\Lunar\Product;
use App\Models\Restaurant;
use Lunar\FieldTypes\Text;
use Lunar\Models\Language;

beforeEach(fn () => Language::factory()->create(['code' => 'en', 'default' => true]));

test('search finds active restaurants by name', function () {
    Restaurant::factory()->active()->create(['name' => 'Napoli Frozen Kitchen', 'description' => 'Wood-fired pizzas']);
    Restaurant::factory()->active()->create(['name' => 'Bayou Bites']);

    $results = (new SearchMarketplaceAction)->handle('Napoli');

    expect($results['restaurants'])->toHaveCount(1)
        ->and($results['restaurants']->first()->name)->toBe('Napoli Frozen Kitchen');
});

test('draft restaurants are hidden from search', function () {
    Restaurant::factory()->draft()->create(['name' => 'Napoli Frozen Kitchen']);
    Restaurant::factory()->active()->create(['name' => 'Napoli Express']);

    $results = (new SearchMarketplaceAction)->handle('Napoli');

    expect($results['restaurants'])->toHaveCount(1)
        ->and($results['restaurants']->first()->name)->toBe('Napoli Express');
});

test('search finds categories by name', function () {
    Collection::factory()->create([
        'attribute_data' => collect(['name' => new Text('Frozen Pizzas')]),
    ]);
    Collection::factory()->create([
        'attribute_data' => collect(['name' => new Text('Ice Cream')]),
    ]);

    $results = (new SearchMarketplaceAction)->handle('Pizza');

    expect($results['categories'])->toHaveCount(1)
        ->and($results['categories']->first()->displayName())->toBe('Frozen Pizzas');
});

test('search finds products by name and by restaurant name', function () {
    $restaurant = Restaurant::factory()->active()->create(['name' => 'Napoli Frozen Kitchen']);

    Product::factory()->create([
        'restaurant_id' => $restaurant->id,
        'attribute_data' => collect([
            'name' => new Text('Lobster Ravioli'),
            'description' => new Text('Wild caught lobster'),
        ]),
    ]);
    Product::factory()->create([
        'restaurant_id' => $restaurant->id,
        'attribute_data' => collect([
            'name' => new Text('Margherita Pizza'),
            'description' => new Text('San Marzano tomatoes'),
        ]),
    ]);

    $byProductName = (new SearchMarketplaceAction)->handle('Ravioli');
    expect($byProductName['products'])->toHaveCount(1)
        ->and($byProductName['products']->first()->displayName())->toBe('Lobster Ravioli');

    $byRestaurantName = (new SearchMarketplaceAction)->handle('Napoli');
    expect($byRestaurantName['products'])->toHaveCount(2)
        ->and($byRestaurantName['restaurants'])->toHaveCount(1);
});

test('a single query returns all three result groups', function () {
    $restaurant = Restaurant::factory()->active()->create(['name' => 'Pizza Palace']);
    Collection::factory()->create([
        'attribute_data' => collect(['name' => new Text('Pizza Classics')]),
    ]);
    Product::factory()->create([
        'restaurant_id' => $restaurant->id,
        'attribute_data' => collect([
            'name' => new Text('Pizza Dough Kit'),
            'description' => new Text('48-hour fermented'),
        ]),
    ]);

    $results = (new SearchMarketplaceAction)->handle('Pizza');

    expect($results['restaurants'])->toHaveCount(1)
        ->and($results['categories'])->toHaveCount(1)
        ->and($results['products'])->toHaveCount(1);
});
