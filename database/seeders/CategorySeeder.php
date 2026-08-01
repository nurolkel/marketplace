<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Seed the platform-curated category taxonomy.
     */
    public function run(): void
    {
        $taxonomy = [
            'Italian',
            'Pizzeria',
            'Hot Plates',
            'Desserts',
            'Ice Cream',
            'Vegan',
            'Seafood',
            'Bakery',
        ];

        foreach ($taxonomy as $name) {
            Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );
        }
    }
}
