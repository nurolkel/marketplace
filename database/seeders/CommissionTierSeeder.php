<?php

namespace Database\Seeders;

use App\Models\CommissionTier;
use Illuminate\Database\Seeder;

class CommissionTierSeeder extends Seeder
{
    /**
     * The platform's starting commission scale. Rates are in basis
     * points; the default tier is the platform standard.
     */
    public function run(): void
    {
        $tiers = [
            ['name' => 'Standard', 'slug' => 'standard', 'rate' => 1500, 'is_default' => true, 'sort_order' => 1],
            ['name' => 'Preferred', 'slug' => 'preferred', 'rate' => 1000, 'is_default' => false, 'sort_order' => 2],
            ['name' => 'Partner', 'slug' => 'partner', 'rate' => 500, 'is_default' => false, 'sort_order' => 3],
        ];

        foreach ($tiers as $tier) {
            CommissionTier::firstOrCreate(['slug' => $tier['slug']], $tier);
        }
    }
}
