<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminSubscriptionController extends Controller
{
    public function adminSubscriptionDashboard(Request $request): JsonResponse
    {
        try {
            $subscriptionQuery = Subscription::query()
                ->with([
                    'user:id,name',
                    'items.plan',
                ]);

            if ($request->filled('plan_id')) {
                    $subscriptionQuery->whereHas('items', function ($q) use ($request) {
                        $q->whereHas('plan', function ($q2) use ($request) {
                            $q2->where('id', $request->plan_id);
                        });
                    });
                }

            if ($request->filled('status')) {
                match ($request->status) {
                    'active' => $subscriptionQuery->where('stripe_status', 'active'),

                    'trial' => $subscriptionQuery
                        ->whereNotNull('trial_ends_at')
                        ->where('trial_ends_at', '>', now()),

                    'canceled' => $subscriptionQuery->whereNotNull('ends_at'),

                    'expired' => $subscriptionQuery
                        ->whereNotNull('ends_at')
                        ->where('ends_at', '<', now()),

                    default => null,
                };
            }

            if ($request->filled('search')) {
                $search = $request->search;

                $subscriptionQuery->where(function ($query) use ($search) {
                    $query->where('id', 'like', "%{$search}%")

                        ->orWhereHas('user', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        })

                        ->orWhereHas('items.plan', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                });
           }
  
            $subscriptionQuery->orderBy(
                'created_at',
                $request->get('sort', 'desc')
            );

            $subscriptions = $subscriptionQuery
                ->paginate($request->get('per_page', 10))
                ->through(function ($subscription) {

                    $plan = $subscription->items->first()?->plan;
                    $renewOn = $subscription->renewOn();

                    return [
                        'subscription_id' => $subscription->id,
                        'customer_name' => $subscription->user->name,
                        'plan' => $plan?->name,
                        'amount' => $plan?->price,
                        'renewal' => ! $subscription->cancel_at_period_end,
                        'start_date' => $subscription->created_at->toDateTimeString(),
                        'next_billing' => $renewOn?->format('Y-m-d'),
                        'status' => $this->resolveStatus($subscription),
                    ];
                });

            $monthlyRevenue = Subscription::activeLike()
                ->whereMonth('created_at', Carbon::now()->month)
                ->whereYear('created_at', Carbon::now()->year)
                ->with('items.plan')
                ->get()
                ->sum(function ($subscription) {
                       return $subscription->items->sum(function ($item) {
                         return ($item->quantity ?? 1) * ($item->plan->price ?? 0);
                    });
                });

            $totalActive = Subscription::where('stripe_status', 'active')->count();

            $renewals = Subscription::whereMonth('updated_at', now()->month)
                ->where('stripe_status', 'active')
                ->count();

            $cancellations = Subscription::whereNotNull('ends_at')
                ->whereMonth('ends_at', now()->month)
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'Admin subscription dashboard loaded successfully',
                'data' => [
                    'subscriptions' => $subscriptions,
                     'monthlyRevenue' => $monthlyRevenue,
                     'totalActive' => $totalActive,
                     'renewals' => $renewals,
                    'cancellations' => $cancellations,
                ],
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load admin subscription dashboard',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function resolveStatus($subscription): string
    {
        if ($subscription->stripe_status === 'active') {
            return 'Active';
        }
        if ($subscription->trial_ends_at && $subscription->trial_ends_at->isFuture()) {
            return 'Trial';
        }
        if ($subscription->ends_at && $subscription->ends_at->isPast()) {
            return 'Expired';
        }
        if ($subscription->cancel_at_period_end) {
            return 'Cancelled';
        }
        return 'Unknown';
    }

    public function changeSubscription(Request $request): JsonResponse
    {
            $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id',
            'plan_id' => 'required|exists:plans,id',
        ]);

        DB::beginTransaction();

        try {
            
            $subscription = Subscription::with('items')->findOrFail($request->subscription_id);
            $newPlan = Plan::findOrFail($request->plan_id);

            if (! $newPlan->stripe_price_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected plan is not linked to Stripe',
                ], 422);
            }

            $currentItem = $subscription->items->first();

            if ($currentItem?->stripe_price === $newPlan->stripe_price_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is already on this plan',
                ], 409);
            }

            $subscription->swap($newPlan->stripe_price_id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Subscription plan updated successfully',
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to change subscription plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function changeStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:cancel,cancel_at_period_end,resume',
        ]);

        DB::beginTransaction();

        try {

            $subscription = Subscription::findOrFail($id);

            match($request->action) {
                'cancel' => $subscription->cancelNow(),
                'cancel_at_period_end' => $subscription->cancel(),
                'resume' => $subscription->resume(),
                default => null,
            };

            DB::commit(); 

            return response()->json([
                'success' => true,
                'message' => 'Subscription status updated successfully',
            ], 200);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update subscription status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteSubscription(int $id): JsonResponse
    {
        try {
            
            DB::transaction(function () use ($id) {

                $subscription = Subscription::with('items')->findOrFail($id);

                $subscription->items()->delete();
                $subscription->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Subscription deleted successfully',
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getCustomerList(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'search'   => 'nullable|string|max:255',
                'status'   => 'nullable|in:active,inactive,pending,deleted',
                'plan_id'  => 'nullable|exists:plans,id',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $query = User::query()
                ->with([
                    'subscriptions.items.plan',
                ]);

            if ($request->filled('search')) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%');
                });
            }

            if ($request->filled('plan_id')) {
                $query->whereHas('subscriptions.items.plan', function ($q) use ($request) {
                    $q->where('id', $request->plan_id);
                });
            }

            if ($request->filled('status')) {
                match ($request->status) {
                    'active' => $query->whereHas('subscriptions', fn ($q) =>
                        $q->where('stripe_status', 'active')
                    ),

                    'inactive' => $query->whereDoesntHave('subscriptions'),

                    'pending' => $query->whereHas('subscriptions', fn ($q) =>
                        $q->where('stripe_status', 'trialing')
                    ),

                    'deleted' => $query->whereHas('subscriptions', fn ($q) =>
                        $q->whereNotNull('ends_at')
                    ),

                    default => null,
                };
            }

            $allowedSortColumns = ['created_at', 'name', 'email'];
            $allowedDirections  = ['asc', 'desc'];

            $sortBy        = $request->get('sort_by', 'created_at');
            $sortDirection = strtolower($request->get('sort', 'desc'));

            if (! in_array($sortBy, $allowedSortColumns)) {
                $sortBy = 'created_at';
            }

            if (! in_array($sortDirection, $allowedDirections)) {
                $sortDirection = 'desc';
            }

            $query->orderBy($sortBy, $sortDirection);

            $customers = $query
                ->latest()
                ->paginate($request->get('per_page', 10))
                ->through(function ($user, $index) {

                    $subscription = $user->subscriptions->first();
                    $plan = $subscription?->items->first()?->plan;

                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'contact' => [
                            'email' => $user->email,
                            'phone' => $user->phone,
                        ],
                        'plan' => $plan?->name,
                        'status' => $this->resolveCustomerStatus($subscription),
                        'created_at' => $user->created_at->toDateTimeString(),
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Customer list loaded successfully',
                'data' => $customers,
                'meta' => [
                    'sort_by' => $sortBy,
                    'sort' => $sortDirection,
                ],
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load customers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function resolveCustomerStatus($subscription): string
    {
        if (! $subscription) {
            return 'Inactive';
        }
        if ($subscription->ends_at && $subscription->ends_at->isPast()) {
            return 'Deleted';
        }
        if ($subscription->onTrial()) {
            return 'Pending';
        }
        if ($subscription->active()) {
            return 'Active';
        }
        return 'Inactive';
    }

    public function customerSubscription(Int $id): JsonResponse
    {
        try{
            $user = User::findOrFail($id);
            $subscription = $user->subscription('default');
            $paymentMethods = $user->paymentMethods();

            if (!$subscription) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No active subscription'
                ]);
            }
            
            $priceId = $subscription->items->first()->stripe_price;
            $plan = Plan::where('stripe_price_id', $priceId)->first();
            $amount = $plan->price;
            $renewOn = $subscription->renewOn();
            $displayEndDate = $subscription->displayEndDate();
            $displayStartDate = $subscription->displayStartDate();
            $getPlan = $subscription->getPlan();
            
            $formattedMethods = [];
            
            foreach ($paymentMethods as $method) {
                $formattedMethods[] = [
                    'type' => $method->type,
                    'card' => [
                        'brand' => $method->card->brand,
                        'last4' => $method->card->last4,
                        'exp_month' => $method->card->exp_month,
                        'exp_year' => $method->card->exp_year,
                    ],
                    'is_default' => $user->defaultPaymentMethod()?->id === $method->id,
                ];
            }

            $data = [
                'plan' => $getPlan,
                'stripe_status' => $subscription->stripe_status,
                'billing_cycle' => $plan->interval,
                'monthly_rate' => $amount,
                'start' => $displayStartDate?->format('Y-m-d'),
                'ends' => $displayEndDate?->format('Y-m-d'),
                'renew_on' =>  $renewOn->format('Y-m-d'),
                'monthly_renewal' => $plan->interval,
                'active' => $subscription->active(),
                'card_info' =>  $formattedMethods,
            ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
  
        } catch (\Throwable $e){
             return response()->json([
                'success' => false,
                'message' => 'Failed to get customer subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function billingHistory(Request $request, Int $id ): JsonResponse
    {
        try {
             $request->validate([
                'search'   => 'nullable|string|max:255',
                'sort_by'  => 'nullable|in:date,amount,invoice',
                'sort'     => 'nullable|in:asc,desc',
                'per_page' => 'nullable|integer|min:1|max:50',
            ]);


            $user = User::findOrFail($id);
            $subscription = $user->subscription('default');
            $plan = $subscription->getPlan();

            $invoices = collect($user->invoices());

            if ($request->filled('search')) {
                $search = strtolower($request->search);

                $invoices = $invoices->filter(function ($invoice) use ($search) {
                    return Str::contains(strtolower($invoice->number), $search)
                        || Str::contains(strtolower($invoice->description ?? ''), $search)
                        || Str::contains(strtolower($invoice->payment_method_details['type'] ?? ''), $search);
                });
            }

            $sortBy = $request->get('sort_by', 'date');
            $direction = $request->get('sort', 'desc');

            $invoices = $invoices->sortBy(function ($invoice) use ($sortBy) {
                return match ($sortBy) {
                    'amount'  => $invoice->total,
                    'invoice' => $invoice->number,
                    default   => $invoice->created,
                };
            }, SORT_REGULAR, $direction === 'desc');

            $perPage = $request->get('per_page', 10);
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $pagedData = $invoices->slice(($currentPage - 1) * $perPage, $perPage)->values();

            $paginatedInvoices = new LengthAwarePaginator(
                $pagedData,
                $invoices->count(),
                $perPage,
                $currentPage,
                ['path' => request()->url(), 'query' => request()->query()]
            );

            $data = $paginatedInvoices->through(function ($invoice) use ($plan) {
                return [
                    'invoice_id' => $invoice->number,
                    'date'       => $invoice->date()->format('M d, Y'),
                    'plan'       => $plan ?? '—',
                    'method'     => ucfirst($invoice->payment_method_details['type'] ?? 'Card'),
                    'amount'     => '$' . number_format($invoice->total / 100, 2),
                    'download'   => $invoice->invoice_pdf,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Billing history loaded successfully',
                'data'    => $data,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load billing history',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function allBillingHistory(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'search'   => 'nullable|string|max:255',
                'sort_by'  => 'nullable|in:date,amount,invoice',
                'sort'     => 'nullable|in:asc,desc',
                'per_page' => 'nullable|integer|min:1|max:50',
            ]);

            $planMap = Plan::pluck('name', 'stripe_price_id');
            $users = User::all()->filter(fn ($user) => count($user->invoices()) > 0);

            $invoices = collect();

            foreach ($users as $user) {
                foreach ($user->invoices() as $invoice) {

                    $stripePriceId = null;

                    foreach ($invoice->lines->data as $line) {
                        if (!empty($line->pricing?->price_details?->price)) {
                            $stripePriceId = $line->pricing->price_details->price;
                            break;
                        }
                    }

                    $planName = $stripePriceId ? ($planMap[$stripePriceId] ?? '—') : '—';

                        $invoices->push([
                            'user_name' => $user->name,
                            'user_id'   => $user->id,
                            'invoice'   => $invoice->number,
                            'amount'    => $invoice->total,
                            'date'      => $invoice->created,
                            'method'    => $invoice->payment_method_details['type'] ?? 'card',
                            'plan'      => $planName ?? '-',
                            'download'  => $invoice->invoice_pdf,
                        ]);
                }
           }

            if ($request->filled('search')) {
                $search = strtolower($request->search);

                $invoices = $invoices->filter(function ($invoice) use ($search) {
                    return Str::contains(strtolower($invoice['user_name']), $search)
                        || Str::contains(strtolower($invoice['invoice']), $search)
                        || Str::contains(strtolower($invoice['plan']), $search)
                        || Str::contains(strtolower($invoice['method']), $search);
                });
            }

            $sortBy = $request->get('sort_by', 'date');
            $direction = $request->get('sort', 'desc');

            $invoices = $invoices->sortBy(
                fn ($invoice) => match ($sortBy) {
                    'amount'  => $invoice['amount'],
                    'invoice' => $invoice['invoice'],
                    default   => $invoice['date'],
                },
                SORT_REGULAR,
                $direction === 'desc'
            );

            $perPage = (int) $request->get('per_page', 10);
            $currentPage = LengthAwarePaginator::resolveCurrentPage();

            $pagedData = $invoices
                ->slice(($currentPage - 1) * $perPage, $perPage)
                ->values();

            $paginator = new LengthAwarePaginator(
                $pagedData,
                $invoices->count(),
                $perPage,
                $currentPage,
                [
                    'path'  => $request->url(),
                    'query' => $request->query(),
                ]
            );

            $data = $paginator->through(fn ($invoice) => [
                'name'     => $invoice['user_name'],
                'invoice'  => $invoice['invoice'],
                'plan'     => $invoice['plan'],
                'amount'   => '$' . number_format($invoice['amount'] / 100, 2),
                'method'   => ucfirst($invoice['method']),
                'date'     => date('M d, h:i A', $invoice['date']),
                'download' => $invoice['download'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Customer billing history loaded successfully',
                'data'    => $data,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load billing history',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

}