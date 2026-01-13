<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Cashier\Cashier;
use App\Models\Plan;
use Stripe\StripeClient;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    protected $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('cashier.secret'));
    }

    public function index()
    {
        try {
            $plan = Plan::firstOrFail();
            $start_date = Carbon::now();
            $end_date = $start_date->copy()->addMonth();

            $data = [
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => $plan->price,
                'start date' => $start_date,
                'end date' => $end_date,
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch plans',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function syncFromStripe()
    {
        try {
            $products = $this->stripe->products->all(['active' => true]);

            foreach ($products->data as $product) {
                $prices = $this->stripe->prices->all([
                    'product' => $product->id,
                    'active' => true
                ]);

                foreach ($prices->data as $price) {
                    Plan::updateOrCreate(
                        ['stripe_price_id' => $price->id],
                        [
                            'name' => $product->name,
                            'stripe_product_id' => $product->id,
                            'price' => $price->unit_amount / 100,
                            'currency' => strtoupper($price->currency),
                            'interval' => $price->recurring->interval ?? 'one_time',
                            'interval_count' => $price->recurring->interval_count ?? 1,
                            'description' => $product->description,
                            'features' => $product->metadata->features ?? null,
                        ]
                    );
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Plans synced successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync plans',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
