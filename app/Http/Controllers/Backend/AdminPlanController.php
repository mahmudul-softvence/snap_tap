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
            $query = Plan::query()
                      ->with(['subscriptionItems.subscription']);

            if ($request->filled('status')) {
                match ($request->status) {
                    'active'   => $query->where('is_active', 1),
                    'inactive' => $query->where('is_active', 0),
                    default    => null,
                };
            }

            if ($request->filled('plan_id')) {
                $query->where('id', $request->plan_id);
            }
            
            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
                });
            }

            $sortDirection = $request->get('sort', 'desc');
            $query->orderBy('created_at', $sortDirection);

             $plans = $query->paginate(
                $request->integer('per_page', 10)
            );

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
                    'currency' => $plan->currency,
                    'billing_cycle' => ucfirst($plan->interval),
                    'status' => $plan->is_active ? 'active' : 'inactive',
                    'total_subscribers' => $activeSubscribers,
                    'features' => $plan->features,
                    'platforms' => $plan->platforms,
                    'request_credits' => $plan->request_credits,
                    'review_reply_credits' => $plan->review_reply_credits,
                    'total_ai_agent' => $plan->total_ai_agent,
                    'created_on' => $plan->created_at->toDateString(),
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
            'name'          => 'required|string',
            'price'         => 'required|numeric|min:0',
            'currency'      => 'required|string|size:3',
            'billing_cycle' => 'required|in:monthly,quarterly,biannual,yearly',
        ]);

        $supportedCurrencies = [
            'AED','AFN','ALL','AMD','ANG','AOA','ARS','AUD','AWG','AZN',
            'BAM','BBD','BDT','BGN','BIF','BMD','BND','BOB','BRL','BSD',
            'BWP','BYN','BZD','CAD','CDF','CHF','CLP','CNY','COP','CRC',
            'CVE','CZK','DJF','DKK','DOP','DZD','EGP','ETB','EUR','FJD',
            'FKP','GBP','GEL','GIP','GMD','GNF','GTQ','GYD','HKD','HNL',
            'HRK','HTG','HUF','IDR','ILS','INR','ISK','JMD','JPY','KES',
            'KGS','KHR','KMF','KRW','KYD','KZT','LAK','LBP','LKR','LRD',
            'LSL','MAD','MDL','MGA','MKD','MMK','MNT','MOP','MUR','MVR',
            'MWK','MXN','MYR','MZN','NAD','NGN','NIO','NOK','NPR','NZD',
            'PAB','PEN','PGK','PHP','PKR','PLN','PYG','QAR','RON','RSD',
            'RUB','RWF','SAR','SBD','SCR','SEK','SGD','SHP','SLE','SOS',
            'SRD','STD','SZL','THB','TJS','TOP','TRY','TTD','TWD','TZS',
            'UAH','UGX','USD','UYU','UZS','VND','VUV','WST','XAF','XCD',
            'XOF','XPF','YER','ZAR','ZMW'
        ];

        $currency = strtoupper($request->currency);

        if (! in_array($currency, $supportedCurrencies)) {
            abort(422, 'Unsupported currency');
        }

        $zeroDecimalCurrencies = [
            'BIF','CLP','DJF','GNF','JPY','KMF',
            'KRW','MGA','PYG','RWF','UGX',
            'VND','VUV','XAF','XOF','XPF'
        ];

        $amount = in_array($currency, $zeroDecimalCurrencies) ? (int) $request->price : (int) ($request->price * 100);

        DB::beginTransaction();

        try {
            $stripeProduct = $this->stripe->products->create([
                'name' => $request->name,
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
                'unit_amount' => $amount,
                'currency' => strtolower($currency),
                'recurring' => [
                    'interval' => $cycle['interval'],
                    'interval_count' => $cycle['count'],
                ],
            ]);

            Plan::create([
                'name' => $request->name,
                'price' => $request->price,
                'currency' => $currency,
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

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Plan created successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
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
            'name'          => 'required|string',
            'price'         => 'required|numeric|min:0',
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
                $plan->interval !== $this->mapInterval($request->billing_cycle)['interval'] ||
                $plan->interval_count !== $this->mapInterval($request->billing_cycle)['count'];

            if ($priceChanged) {

                $this->stripe->prices->update(
                    $plan->stripe_price_id,
                    ['active' => false]
                );

                $zeroDecimalCurrencies = [
                    'BIF','CLP','DJF','GNF','JPY','KMF',
                    'KRW','MGA','PYG','RWF','UGX',
                    'VND','VUV','XAF','XOF','XPF'
                ];

                $currency = strtoupper($plan->currency);

                $amount = in_array($currency, $zeroDecimalCurrencies) ? (int) $request->price : (int) ($request->price * 100);

                $cycle = $this->mapInterval($request->billing_cycle);

                $newStripePrice = $this->stripe->prices->create([
                    'product' => $plan->stripe_product_id,
                    'unit_amount' => $amount,
                    'currency' => strtolower($currency),
                    'recurring' => [
                        'interval' => $cycle['interval'],
                        'interval_count' => $cycle['count'],
                    ],
                ]);

                $plan->stripe_price_id = $newStripePrice->id;
                $plan->interval = $cycle['interval'];
                $plan->interval_count = $cycle['count'];
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

        } catch (\Stripe\Exception\ApiErrorException $e) {
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

    private function mapInterval(string $billingCycle): array
    {
        return [
            'monthly'   => ['interval' => 'month', 'count' => 1],
            'quarterly' => ['interval' => 'month', 'count' => 3],
            'biannual'  => ['interval' => 'month', 'count' => 6],
            'yearly'    => ['interval' => 'year',  'count' => 1],
        ][$billingCycle];
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
