<?php

namespace Database\Factories;

use App\Models\CommissionPromotion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CommissionPromotion>
 */
class CommissionPromotionFactory extends Factory
{
    protected $model = CommissionPromotion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().' '.fake()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'rate' => 0,
            'duration_days' => null,
            'max_orders' => null,
            'active' => true,
        ];
    }

    /**
     * Commission-free for the first N days after assignment.
     */
    public function firstDays(int $days): static
    {
        return $this->state(['duration_days' => $days]);
    }

    /**
     * Commission-free for the first N fulfilled orders.
     */
    public function firstOrders(int $orders): static
    {
        return $this->state(['max_orders' => $orders]);
    }

    /**
     * A reduced rather than free promotional rate.
     */
    public function withRate(int $rate): static
    {
        return $this->state(['rate' => $rate]);
    }
}
