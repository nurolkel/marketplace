<?php

namespace Database\Seeders;

use App\Models\CommissionPromotion;
use Illuminate\Database\Seeder;

class CommissionPromotionSeeder extends Seeder
{
    /**
     * The platform's launch promotions. Rates are in basis points
     * (0 = commission-free); a promotion ends at its duration or
     * order cap, whichever comes first.
     */
    public function run(): void
    {
        $promotions = [
            ['name' => 'First 30 days commission-free', 'slug' => 'first-30-days-free', 'rate' => 0, 'duration_days' => 30, 'max_orders' => null],
            ['name' => 'First 100 orders commission-free', 'slug' => 'first-100-orders-free', 'rate' => 0, 'duration_days' => null, 'max_orders' => 100],
            ['name' => 'Half commission for 60 days', 'slug' => 'half-commission-60-days', 'rate' => 750, 'duration_days' => 60, 'max_orders' => null],
        ];

        foreach ($promotions as $promotion) {
            CommissionPromotion::firstOrCreate(['slug' => $promotion['slug']], $promotion);
        }
    }
}
