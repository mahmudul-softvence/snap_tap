<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Models\Subscription;
use Carbon\Carbon;

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

                    return [
                        'subscription_id' => $subscription->id,
                        'customer_name' => $subscription->user->name,
                        'plan' => $plan?->name,
                        'amount' => $plan?->price,
                        'renewal' => ! $subscription->cancel_at_period_end,
                        'start_date' => $subscription->created_at->toDateTimeString(),
                        'next_billing' => $subscription->trial_ends_at
                            ? $subscription->trial_ends_at->toDateTimeString()
                            : $subscription->ends_at?->toDateTimeString(),
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

}