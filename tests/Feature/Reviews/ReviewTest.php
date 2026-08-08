<?php

use App\Actions\Reviews\StoreReviewAction;
use App\Enums\RestaurantOrderStatus;
use App\Models\Lunar\Order;
use App\Models\Restaurant;
use App\Models\RestaurantOrder;
use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Lunar\Models\Currency;
use Lunar\Models\Language;

beforeEach(function () {
    Language::factory()->create(['code' => 'en', 'default' => true]);
    Currency::factory()->create(['default' => true]);

    $this->restaurant = Restaurant::factory()->create();
    $this->customer = User::factory()->create();

    $this->makeSubOrder = function (RestaurantOrderStatus $status = RestaurantOrderStatus::Completed, ?User $customer = null): RestaurantOrder {
        $order = Order::factory()->create(['user_id' => ($customer ?? $this->customer)->id]);

        return RestaurantOrder::factory()->create([
            'order_id' => $order->id,
            'restaurant_id' => $this->restaurant->id,
            'status' => $status,
        ]);
    };
});

test('a customer reviews a restaurant they have a completed sub-order with', function () {
    ($this->makeSubOrder)();

    $response = $this->actingAs($this->customer)->postJson(route('restaurants.reviews.store', $this->restaurant), [
        'rating' => 5,
        'title' => 'Great food',
        'body' => 'Everything was excellent.',
    ]);

    $response->assertCreated()->assertJson([
        'rating' => 5,
        'title' => 'Great food',
        'body' => 'Everything was excellent.',
        'author' => $this->customer->name,
    ]);

    $review = Review::sole();
    expect($review->user_id)->toBe($this->customer->id)
        ->and($review->reviewable_type)->toBe(Restaurant::class)
        ->and($review->reviewable_id)->toBe($this->restaurant->id);
});

test('a customer reviews their completed sub-order', function () {
    $subOrder = ($this->makeSubOrder)();

    $this->actingAs($this->customer)
        ->postJson(route('restaurant-orders.review.store', $subOrder), ['rating' => 4])
        ->assertCreated()
        ->assertJson(['rating' => 4, 'title' => null, 'body' => null]);

    $review = Review::sole();
    expect($review->reviewable_type)->toBe(RestaurantOrder::class)
        ->and($review->reviewable_id)->toBe($subOrder->id)
        ->and($subOrder->review()->is($review))->toBeTrue();
});

test('any account holder reviews the platform', function () {
    $this->actingAs($this->customer)
        ->postJson(route('platform.review.store'), ['rating' => 3, 'title' => 'Decent'])
        ->assertCreated();

    $review = Review::sole();
    expect($review->reviewable_type)->toBeNull()
        ->and($review->reviewable_id)->toBeNull()
        ->and($review->user_id)->toBe($this->customer->id);
});

test('the endpoints reject ratings outside one to five', function (int $rating) {
    $this->actingAs($this->customer)
        ->postJson(route('platform.review.store'), ['rating' => $rating])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rating']);
})->with([
    'zero' => [0],
    'six' => [6],
]);

test('the action rejects ratings outside one to five', function (int $rating) {
    (new StoreReviewAction)->handle($this->customer, null, $rating, null, null);
})->with([
    'zero' => [0],
    'six' => [6],
])->throws(ValidationException::class);

test('a restaurant cannot be reviewed without a completed sub-order', function () {
    $this->actingAs($this->customer)
        ->postJson(route('restaurants.reviews.store', $this->restaurant), ['rating' => 5])
        ->assertForbidden();
});

test('a sub-order that is not completed yet does not unlock restaurant reviews', function () {
    ($this->makeSubOrder)(RestaurantOrderStatus::Dispatched);

    (new StoreReviewAction)->handle($this->customer, $this->restaurant, 5, null, null);
})->throws(AuthorizationException::class);

test("another customer's completed sub-order does not unlock restaurant reviews", function () {
    ($this->makeSubOrder)(RestaurantOrderStatus::Completed, User::factory()->create());

    (new StoreReviewAction)->handle($this->customer, $this->restaurant, 5, null, null);
})->throws(AuthorizationException::class);

test('sub-order reviews are rejected for other customers', function () {
    $subOrder = ($this->makeSubOrder)();

    $this->actingAs(User::factory()->create())
        ->postJson(route('restaurant-orders.review.store', $subOrder), ['rating' => 5])
        ->assertForbidden();
});

test('sub-order reviews require the sub-order to be completed', function (RestaurantOrderStatus $status) {
    $subOrder = ($this->makeSubOrder)($status);

    $this->actingAs($this->customer)
        ->postJson(route('restaurant-orders.review.store', $subOrder), ['rating' => 5])
        ->assertForbidden();
})->with([
    'pending' => [RestaurantOrderStatus::Pending],
    'preparing' => [RestaurantOrderStatus::Preparing],
    'dispatched' => [RestaurantOrderStatus::Dispatched],
    'cancelled' => [RestaurantOrderStatus::Cancelled],
]);

test('re-posting a restaurant review updates it instead of duplicating', function () {
    ($this->makeSubOrder)();

    $this->actingAs($this->customer)
        ->postJson(route('restaurants.reviews.store', $this->restaurant), ['rating' => 2, 'title' => 'Meh']);
    $this->actingAs($this->customer)
        ->postJson(route('restaurants.reviews.store', $this->restaurant), ['rating' => 5, 'title' => 'Better now']);

    $review = Review::sole();
    expect($review->rating)->toBe(5)
        ->and($review->title)->toBe('Better now');
});

test('platform reviews are one per user, updated on re-post', function () {
    $first = $this->actingAs($this->customer)
        ->postJson(route('platform.review.store'), ['rating' => 1]);
    $first->assertCreated();

    $second = $this->actingAs($this->customer)
        ->postJson(route('platform.review.store'), ['rating' => 4, 'body' => 'Improved']);
    $second->assertOk();

    $review = Review::sole();
    expect($review->rating)->toBe(4)
        ->and($review->body)->toBe('Improved')
        ->and($second->json('id'))->toBe($first->json('id'));
});

test('the listing returns reviews newest first with the restaurant average', function () {
    $older = Review::factory()->forReviewable($this->restaurant)->create([
        'rating' => 5,
        'created_at' => now()->subDay(),
    ]);
    $newer = Review::factory()->forReviewable($this->restaurant)->create(['rating' => 4]);
    Review::factory()->create(['rating' => 1]);

    $response = $this->getJson(route('restaurants.reviews.index', $this->restaurant));

    $response->assertOk()
        ->assertJsonPath('average_rating', 4.5)
        ->assertJsonPath('reviews_count', 2)
        ->assertJsonPath('pagination.total', 2)
        ->assertJsonPath('pagination.current_page', 1);

    expect(collect($response->json('reviews'))->pluck('id')->all())->toBe([$newer->id, $older->id]);
});

test('the listing shows no average when the restaurant has no reviews', function () {
    $this->getJson(route('restaurants.reviews.index', $this->restaurant))
        ->assertOk()
        ->assertJsonPath('average_rating', null)
        ->assertJsonPath('reviews_count', 0);
});

test('authors can delete their review', function () {
    $review = Review::factory()->platform()->create(['user_id' => $this->customer->id]);

    $this->actingAs($this->customer)
        ->deleteJson(route('reviews.destroy', $review))
        ->assertOk()
        ->assertJsonPath('message', 'Review deleted.');

    expect(Review::count())->toBe(0);
});

test('reviews cannot be deleted by other users', function () {
    $review = Review::factory()->platform()->create(['user_id' => $this->customer->id]);

    $this->actingAs(User::factory()->create())
        ->deleteJson(route('reviews.destroy', $review))
        ->assertForbidden();

    $this->assertModelExists($review);
});

test('admins can delete any review for moderation', function () {
    $review = Review::factory()->platform()->create(['user_id' => $this->customer->id]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->deleteJson(route('reviews.destroy', $review))
        ->assertOk();

    expect(Review::count())->toBe(0);
});

test('guests cannot post or delete reviews', function () {
    $review = Review::factory()->platform()->create(['user_id' => $this->customer->id]);

    $this->postJson(route('platform.review.store'), ['rating' => 5])->assertUnauthorized();
    $this->deleteJson(route('reviews.destroy', $review))->assertUnauthorized();
});
