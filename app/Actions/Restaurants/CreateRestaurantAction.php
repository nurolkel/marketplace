<?php

namespace App\Actions\Restaurants;

use App\Enums\RestaurantRole;
use App\Enums\RestaurantStatus;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateRestaurantAction
{
    /**
     * Create a restaurant and attach the creator as its owner.
     *
     * @param  array{name: string, description?: string|null}  $data
     */
    public function handle(User $creator, array $data): Restaurant
    {
        return DB::transaction(function () use ($creator, $data): Restaurant {
            $restaurant = Restaurant::create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name']),
                'description' => $data['description'] ?? null,
                'status' => RestaurantStatus::Draft,
            ]);

            $restaurant->members()->attach($creator, [
                'role' => RestaurantRole::Owner->value,
            ]);

            return $restaurant;
        });
    }

    /**
     * Generate a URL-safe slug, including soft-deleted restaurants in
     * the uniqueness check since the unique index still covers them.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Restaurant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
