<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\RestaurantOrder;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /**
     * Define the model's default state: a restaurant review written
     * by a new customer.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reviewable_type' => Restaurant::class,
            'reviewable_id' => Restaurant::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'title' => fake()->optional()->sentence(4),
            'body' => fake()->optional()->paragraph(),
        ];
    }

    /**
     * A review of the marketplace platform itself.
     */
    public function platform(): static
    {
        return $this->state(fn (): array => [
            'reviewable_type' => null,
            'reviewable_id' => null,
        ]);
    }

    /**
     * Point the review at the given restaurant or sub-order.
     */
    public function forReviewable(Restaurant|RestaurantOrder $reviewable): static
    {
        return $this->state(fn (): array => [
            'reviewable_type' => $reviewable->getMorphClass(),
            'reviewable_id' => $reviewable->getKey(),
        ]);
    }
}
