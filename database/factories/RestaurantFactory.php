<?php

namespace Database\Factories;

use App\Enums\RestaurantStatus;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Restaurant>
 */
class RestaurantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 99999),
            'description' => fake()->sentence(),
            'status' => RestaurantStatus::Draft,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => RestaurantStatus::Draft,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => RestaurantStatus::Active,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => RestaurantStatus::Suspended,
        ]);
    }
}
