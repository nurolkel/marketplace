<?php

use App\Actions\Search\SearchRestaurantsAction;
use App\Models\Category;
use App\Models\Restaurant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

test('search finds restaurants by category name', function () {
    $pizzeria = Category::factory()->create(['name' => 'Pizzeria', 'slug' => 'pizzeria']);

    $napoli = Restaurant::factory()->active()->create(['name' => 'Napoli Kitchen']);
    $napoli->categories()->attach($pizzeria);
    Restaurant::factory()->active()->create(['name' => 'Burger Barn']);

    $results = (new SearchRestaurantsAction)->handle('pizzeria');

    expect($results->total())->toBe(1)
        ->and($results->items()[0]->name)->toBe('Napoli Kitchen');
});

test('inactive restaurants are hidden from search and browse', function () {
    Restaurant::factory()->draft()->create(['name' => 'Pizza Draft']);
    Restaurant::factory()->suspended()->create(['name' => 'Pizza Suspended']);
    Restaurant::factory()->active()->create(['name' => 'Pizza Place']);

    $search = (new SearchRestaurantsAction)->handle('pizza');
    $browse = (new SearchRestaurantsAction)->handle(null);

    expect($search->total())->toBe(1)
        ->and($search->items()[0]->name)->toBe('Pizza Place')
        ->and($browse->total())->toBe(1);
});

test('category slug filter narrows results', function () {
    $italian = Category::factory()->create(['name' => 'Italian', 'slug' => 'italian']);
    $bakery = Category::factory()->create(['name' => 'Bakery', 'slug' => 'bakery']);

    $trattoria = Restaurant::factory()->active()->create(['name' => 'Trattoria']);
    $trattoria->categories()->attach($italian);
    $sourdough = Restaurant::factory()->active()->create(['name' => 'Sourdough House']);
    $sourdough->categories()->attach($bakery);

    $browse = (new SearchRestaurantsAction)->handle(null, 'italian');
    $search = (new SearchRestaurantsAction)->handle('trattoria', 'bakery');

    expect($browse->total())->toBe(1)
        ->and($browse->items()[0]->name)->toBe('Trattoria')
        ->and($search->total())->toBe(0);
});

test('restaurants sort by name alphabetically', function () {
    Restaurant::factory()->active()->create(['name' => 'Zebra Eats']);
    Restaurant::factory()->active()->create(['name' => 'Alpha Bites']);
    Restaurant::factory()->active()->create(['name' => 'Mango Meals']);

    $results = (new SearchRestaurantsAction)->handle(null, null, 'name');

    expect($results->getCollection()->pluck('name')->all())
        ->toBe(['Alpha Bites', 'Mango Meals', 'Zebra Eats']);
});

test('restaurants sort by newest first', function () {
    Restaurant::factory()->active()->create(['name' => 'Old Spot', 'created_at' => now()->subDays(3)]);
    Restaurant::factory()->active()->create(['name' => 'New Spot', 'created_at' => now()->subDay()]);

    $results = (new SearchRestaurantsAction)->handle(null, null, 'newest');

    expect($results->getCollection()->pluck('name')->all())
        ->toBe(['New Spot', 'Old Spot']);
});

test('relevance preserves the engine ranking when searching', function () {
    Restaurant::factory()->active()->create(['name' => 'Pizza One']);
    Restaurant::factory()->active()->create(['name' => 'Pizza Two']);

    $results = (new SearchRestaurantsAction)->handle('pizza');
    $expectedOrder = Restaurant::search('pizza')->keys()->map(fn (mixed $id): int => (int) $id)->all();

    expect($results->total())->toBe(2)
        ->and($results->getCollection()->pluck('id')->all())->toBe($expectedOrder);
});

test('an unknown sort falls back to relevance without leaking into the query', function () {
    Restaurant::factory()->active()->create(['name' => 'Pizza Place']);

    $results = (new SearchRestaurantsAction)->handle('pizza', null, 'name; DROP TABLE restaurants; --');

    expect($results->total())->toBe(1)
        ->and(Restaurant::withTrashed()->count())->toBe(1);
});

test('restaurant results are paginated', function () {
    Restaurant::factory()->active()->count(7)->create();

    $results = (new SearchRestaurantsAction)->handle(null, null, 'newest', 3);

    expect($results)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($results->count())->toBe(3)
        ->and($results->total())->toBe(7)
        ->and($results->lastPage())->toBe(3);
});
