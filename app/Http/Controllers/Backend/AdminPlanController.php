<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Plan;
use Stripe\StripeClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\ApiErrorException;
use App\Models\Subscription;

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
           $plans = Plan::query()
                ->with([
                    'subscriptionItems.subscription'
                ])
                ->latest()
                ->paginate($request->integer('per_page', 10));

            $data = $plans->through(function ($plan) {

                $activeSubscribers = $plan->subscriptionItems
                    ->pluck('subscription')
                    ->filter()
                    ->unique('id')
                    ->filter(fn ($sub) => $sub->isActiveLike())
                    ->count();

                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price' => $plan->price,
                    'features' => $plan->features,
                    'billing_cycle' => ucfirst($plan->interval),
                    'trial_days' => $plan->trial_days,
                    'status' => $plan->is_active ? 'active' : 'inactive',
                    'total_subscribers' => $activeSubscribers,
                    'created_on' => $plan->created_at->toDateString(),
                    'platforms' => $plan->platforms,
                    'request_credits' => $plan->request_credits,
                    'review_reply_credits' => $plan->review_reply_credits,
                    'total_ai_agent' => $plan->total_ai_agent,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $data,
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
            'billing_cycle' => 'required|in:monthly,quarterly,biannual,yearly',
        ]);

        try {
            $stripeProduct = $this->stripe->products->create([
                'name' => $request->name,
                'type' => 'service',
            ]);

            $billingMap = [
                'monthly'    => ['interval' => 'month', 'count' => 1],
                'quarterly'  => ['interval' => 'month', 'count' => 3],
                'biannual'   => ['interval' => 'month', 'count' => 6],
                'yearly'     => ['interval' => 'year',  'count' => 1],
            ];

            $cycle = $billingMap[$request->billing_cycle];

            $stripePrice = $this->stripe->prices->create([
                'product' => $stripeProduct->id,
                'unit_amount' => $request->price * 100,
                'currency' => 'usd',
                'recurring' => [
                    'interval' => $cycle['interval'],
                    'interval_count' => $cycle['count'],
                ],
            ]);

            Plan::create([
                'name' => $request->name,
                'price' => $request->price,
                'stripe_product_id' => $stripeProduct->id,
                'stripe_price_id' => $stripePrice->id,
                'platforms' => $request->platforms,
                'request_credits' => $request->request_credits,
                'review_reply_credits' => $request->review_reply_credits,
                'total_ai_agent' => $request->total_ai_agent,
                'stripe_price_id' => $stripePrice->id,
                'interval' => $cycle['interval'],
                'interval_count' => $cycle['count'],
                'features' => $request->features,
                'is_active' => $request->active_plan ? 1 : 0,
                'allow_trial' => $request->start_with_free_trail ? 1 : 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Plan created successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create plan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function editPlan(Request $request, int $id)
    {
        $request->validate([
            'billing_cycle' => 'required|in:monthly,quarterly,biannual,yearly',
        ]);
        
        DB::beginTransaction();

        try {
            $plan = Plan::findOrFail($id);

            $this->stripe->products->update(
                $plan->stripe_product_id,
                ['name' => $request->name]
            );

            $priceChanged =
                $plan->price != $request->price ||
                $plan->billing_cycle != $request->billing_cycle;

            if ($priceChanged) {
                $this->stripe->prices->update(
                    $plan->stripe_price_id,
                    ['active' => false]
                );
                $billingMap = [
                    'monthly'    => ['interval' => 'month', 'count' => 1],
                    'quarterly'  => ['interval' => 'month', 'count' => 3],
                    'biannual'   => ['interval' => 'month', 'count' => 6],
                    'yearly'     => ['interval' => 'year',  'count' => 1],
                ];

                $cycle = $billingMap[$request->billing_cycle];

                $newStripePrice = $this->stripe->prices->create([
                    'product' => $plan->stripe_product_id,
                    'unit_amount' => $request->price * 100,
                    'currency' => 'usd',
                    'recurring' => [
                        'interval' => $cycle['interval'],
                        'interval_count' => $cycle['count'],
                    ],
                ]);

                $plan->stripe_price_id = $newStripePrice->id;
            }

            $plan->update([
                'name' => $request->name,
                'price' => $request->price,
                'platforms' => $request->platforms,
                'request_credits' => $request->request_credits,
                'review_reply_credits' => $request->review_reply_credits,
                'total_ai_agent' => $request->total_ai_agent,
                'features' => $request->features,
                'is_active' => $request->active_plan ? 1 : 0,
                'allow_trial' => $request->start_with_free_trail ? 1 : 0,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Plan updated successfully',
            ]);

        } catch (ApiErrorException $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Stripe error occurred',
                'error' => $e->getMessage(),
            ], 500);

        } catch (\Throwable $e) {

            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Plan update failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function deletePlan(int $id): JsonResponse
    {
        validator(['id' => $id], [
            'id' => 'required|integer|exists:plans,id',
        ])->validate();

        DB::beginTransaction();

        try {
            $plan = Plan::findOrFail($id);

            $hasSubscriptions = Subscription::whereHas('items', function ($q) use ($plan) {
                $q->where('stripe_price', $plan->stripe_price_id);
            })->exists();

            $this->stripe->prices->update(
                $plan->stripe_price_id,
                ['active' => false]
            );

            if ($hasSubscriptions) {

                $plan->update(['is_active' => 0]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Plan retired. Existing subscribers unaffected.',
                ]);
            }

            $plan->forceDelete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Unused plan removed locally. Stripe data preserved.',
            ]);

        } catch (ApiErrorException $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Stripe error occurred',
                'error' => $e->getMessage(),
            ], 500);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Plan deletion failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
}
