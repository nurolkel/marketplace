<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\RestaurantOrder;
use App\Models\RestaurantPayout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantPayout>
 */
class RestaurantPayoutFactory extends Factory
{
    protected $model = RestaurantPayout::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_order_id' => RestaurantOrder::factory(),
            'restaurant_id' => Restaurant::factory(),
            'gross_amount' => 5000,
            'commission_amount' => 750,
            'net_amount' => 4250,
            'status' => 'pending',
            'eligible_at' => now()->addDays(30),
            'paid_at' => null,
        ];
    }

    /**
     * A payout whose escrow window has already passed.
     */
    public function eligible(): static
    {
        return $this->state([
            'eligible_at' => now()->subDay(),
        ]);
    }

    /**
     * A payout that has been paid out.
     */
    public function paid(): static
    {
        return $this->state([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }
}
