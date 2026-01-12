<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Plan;
use Stripe\StripeClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class AdminPlanController extends Controller
{
    protected $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('cashier.secret'));
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $plans = Plan::with([
                    'subscriptionItems.subscription'
                ])
                ->orderByDesc('created_at')
                ->paginate($request->get('per_page', 10));

            return response()->json([
                'success' => true,
                'message' => 'Plans fetched successfully',
                'data' => $plans->through(function ($plan) {

                    $subscriptions = $plan->subscriptionItems
                        ->pluck('subscription')
                        ->filter()
                        ->unique('id');

                    $activeStatuses = ['active', 'trialing', 'past_due'];

                    $totalSubscribers = $subscriptions
                        ->whereIn('stripe_status', $activeStatuses)
                        ->count();

                    return [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'price' => $plan->price,
                        'billing_cycle' => ucfirst($plan->billing_cycle),
                        'status' => $plan->is_active ? 'active' : 'inactive',
                        'total_subscribers' => $totalSubscribers,
                        'created_on' => $plan->created_at->format('Y-m-d'),
                    ];
                }),
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch plans',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

     
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'billing_cycle' => 'required|string|in:month,year',
            'features' => 'nullable|array',
        ]);

        try {

            $stripeProduct = $this->stripe->products->create([
                'name' => $request->name,
                'type' => 'service',
            ]);

            $stripePrice = $this->stripe->prices->create([
                'product' => $stripeProduct->id,
                'unit_amount' => $request->price * 100, 
                'currency' => 'usd', 
                'recurring' => [
                    'interval' => $request->billing_cycle,
                    'interval_count' => 1,
                ],
            ]);

            $plan = Plan::create([
                'name' => $request->name,
                'price' => $request->price,
                'billing_cycle' => $request->billing_cycle,
                'stripe_product_id' => $stripeProduct->id,
                'stripe_price_id' => $stripePrice->id,
                'platforms' => $request->platforms,
                'request_credits' => $request->request_credits,
                'review_reply_credits' => $request->review_reply_credits,
                'total_ai_agent' => $request->total_ai_agent,
                'stripe_price_id' => $stripePrice->id,
                'features' => $request->features,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Plan created successfully',
                'data' => $plan,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create plan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
}
