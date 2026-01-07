<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Plan;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Pro Plan',
                'price' => 49.00,
                'interval' => 'month',
                'description' => 'Basic plan for individuals',
                'features' => json_encode('Feature 1'),
                'sort_order' => 1,
                'stripe_product_id' => 'prod_TkJeiAmc0yeDmY',
                'stripe_price_id' => 'price_1Smp0x3Ctm6CRT1TLiqBhzxV'
            ],
        ];

        foreach ($plans as $plan) {
            Plan::create($plan);
        }
    }
}
