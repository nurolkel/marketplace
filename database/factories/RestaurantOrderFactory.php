<?php

namespace Database\Factories;

use App\Enums\RestaurantOrderStatus;
use App\Models\Lunar\Order;
use App\Models\Restaurant;
use App\Models\RestaurantOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RestaurantOrder>
 */
class RestaurantOrderFactory extends Factory
{
    protected $model = RestaurantOrder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = fake()->numberBetween(500, 25000);

        return [
            'order_id' => Order::factory(),
            'restaurant_id' => Restaurant::factory(),
            'reference' => 'RO-'.Str::upper(Str::random(10)),
            'status' => RestaurantOrderStatus::Pending,
            'sub_total' => $total,
            'total' => $total,
        ];
    }

    /**
     * A marketplace-owned sub-order with no restaurant attached.
     */
    public function marketplaceOwned(): static
    {
        return $this->state(fn (): array => [
            'restaurant_id' => null,
        ]);
    }

    public function paymentReceived(): static
    {
        return $this->state(fn (): array => [
            'status' => RestaurantOrderStatus::PaymentReceived,
            'placed_at' => now(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => RestaurantOrderStatus::Accepted,
            'placed_at' => now(),
            'accepted_at' => now(),
        ]);
    }

    public function preparing(): static
    {
        return $this->state(fn (): array => [
            'status' => RestaurantOrderStatus::Preparing,
            'placed_at' => now(),
            'accepted_at' => now(),
            'preparing_at' => now(),
        ]);
    }

    public function dispatched(): static
    {
        return $this->state(fn (): array => [
            'status' => RestaurantOrderStatus::Dispatched,
            'placed_at' => now(),
            'accepted_at' => now(),
            'preparing_at' => now(),
            'dispatched_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => RestaurantOrderStatus::Completed,
            'placed_at' => now(),
            'accepted_at' => now(),
            'preparing_at' => now(),
            'dispatched_at' => now(),
            'completed_at' => now(),
        ]);
    }

    /**
     * An on-hold sub-order paused from the given status.
     */
    public function onHold(RestaurantOrderStatus $pausedFrom = RestaurantOrderStatus::Preparing): static
    {
        return $this->state(fn (): array => [
            'status' => RestaurantOrderStatus::OnHold,
            'placed_at' => now(),
            'accepted_at' => now(),
            'paused_from_status' => $pausedFrom->value,
            'pause_reason' => fake()->sentence(),
            'paused_at' => now(),
        ]);
    }
}
