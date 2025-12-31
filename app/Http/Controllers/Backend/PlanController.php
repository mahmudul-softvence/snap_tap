<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Plan;
use Stripe\StripeClient;
class PlanController extends Controller
{
    protected $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('cashier.secret'));
    }

    
    // Get all active plans
     
    public function index()
    {
        try {
            $plans = Plan::active()->ordered()->get();
            
            return response()->json([
                'success' => true,
                'data' => $plans
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch plans',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    
    // Sync plans from Stripe (Admin function)
     
    public function syncFromStripe()
    {
        try {
            // Fetch products from Stripe
            $products = $this->stripe->products->all(['active' => true]);
            
            foreach ($products->data as $product) {
                // Get prices for this product
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
