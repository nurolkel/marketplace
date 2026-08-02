<?php

namespace Database\Factories;

use App\Models\CommissionTier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CommissionTier>
 */
class CommissionTierFactory extends Factory
{
    protected $model = CommissionTier::class;

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
            'rate' => fake()->numberBetween(500, 2000),
            'is_default' => false,
            'sort_order' => 0,
        ];
    }

    /**
     * The platform's standard tier.
     */
    public function standard(): static
    {
        return $this->state([
            'name' => 'Standard',
            'slug' => 'standard',
            'rate' => 1500,
            'is_default' => true,
        ]);
    }
}
