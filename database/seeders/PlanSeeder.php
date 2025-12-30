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
                'price' => 42.00,
                'interval' => 'month',
                'description' => 'Basic plan for individuals',
                'features' => json_encode('Feature 1'),
                'sort_order' => 1,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::create($plan);
        }
    }
}
