<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Plan;
use Illuminate\Support\Facades\Log;
// use Laravel\Cashier\Subscription;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class SubscriptionController extends Controller
{
        public function show(Request $request): JsonResponse
        {
            try {
                $user = $request->user();
                
                $subscription = $user->subscription('default');
                
                if (!$subscription) {
                    return response()->json([
                        'success' => true,
                        'data' => null,
                        'message' => 'No active subscription'
                    ]);
                }
                
                $data = [
                    'id' => $subscription->id,
                    'name' => $subscription->name,
                    'stripe_id' => $subscription->stripe_id,
                    'stripe_status' => $subscription->stripe_status,
                    'stripe_price' => $subscription->stripe_price,
                    'quantity' => $subscription->quantity,
                    'trial_ends_at' => $subscription->trial_ends_at,
                    'ends_at' => $subscription->ends_at,
                    'created_at' => $subscription->created_at,
                    'updated_at' => $subscription->updated_at,
                    'on_trial' => $subscription->onTrial(),
                    'cancelled' => $subscription->cancelled(),
                    'on_grace_period' => $subscription->onGracePeriod(),
                    'active' => $subscription->active(),
                ];
                
                return response()->json([
                    'success' => true,
                    'data' => $data
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch subscription',
                    'error' => $e->getMessage()
                ], 500);
            }
        } 

        public function store(Request $request): JsonResponse
        {

        $request->validate([
            'plan_id' => 'required|exists:plans,id',    
            'payment_method' => 'required|string',
        ]);
        
        try {
            $user = $request->user();
            $plan = Plan::findOrFail($request->plan_id);
            
            // Check if user already has active subscription
            if ($user->subscribed('default')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an active subscription'
                ], 400);
            }

            if (!$user->hasStripeId()) {
               $user->createAsStripeCustomer();
            }

            //  $user->createOrGetStripeCustomer();
            $paymentMethodExists = false;
            foreach ($user->paymentMethods() as $pm) {
                if ($pm->id === $request->payment_method_id) {
                    $paymentMethodExists = true;
                    break;
                }
            }

                if (!$paymentMethodExists) {
                $user->addPaymentMethod($request->payment_method_id);
            }
            
            // Create or update payment method
            $user->updateDefaultPaymentMethod($request->payment_method);
            
            // Create subscription
            $subscription = $user->newSubscription('default', $plan->stripe_price_id);
            
            if ($plan->trial_days > 30) {
                $subscription->trialDays($plan->trial_days);
            }
            
            $subscription->create($request->payment_method);

            $user->refresh();

            $subscriptionData = $user->subscription('default');
            
            return response()->json([
                'success' => true,
                'message' => 'Subscription created successfully',
                'data' => [
                    'subscription' => $subscriptionData,
                    'plan' => $plan,
                    'payment_method_used' => $user->defaultPaymentMethod()?->id
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Subscription creation failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function swap(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);
        
        try {
            $user = $request->user();
            $plan = Plan::findOrFail($request->plan_id);
            
            if (!$user->subscribed('default')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active subscription found'
                ], 400);
            }
            
            // Swap subscription
            $user->subscription('default')->swap($plan->stripe_price_id);
            
            return response()->json([
                'success' => true,
                'message' => 'Subscription swapped successfully',
                'data' => [
                    'subscription' => $user->subscription('default'),
                    'plan' => $plan
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to swap subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

     public function cancel(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user->subscribed('default')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active subscription found'
                ], 400);
            }
            
            // Cancel at period end
            $subscription = $user->subscription('default')->cancel();
            
            return response()->json([
                'success' => true,
                'message' => 'Subscription cancelled successfully',
                'data' => [
                    'ends_at' => $subscription->ends_at,
                    'on_grace_period' => $subscription->onGracePeriod()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function resume(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user->subscription('default')->onGracePeriod()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No subscription on grace period'
                ], 400);
            }
            
            $user->subscription('default')->resume();
            
            return response()->json([
                'success' => true,
                'message' => 'Subscription resumed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resume subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function invoices(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $invoices = $user->invoices();
            
            $formattedInvoices = [];
            
            foreach ($invoices as $invoice) {
                $formattedInvoices[] = [
                    'id' => $invoice->id,
                    'total' => $invoice->total / 100,
                    'currency' => strtoupper($invoice->currency),
                    'status' => $invoice->status,
                    'paid' => $invoice->paid,
                    'invoice_pdf' => $invoice->invoice_pdf,
                    'created' => $invoice->created,
                    'period_start' => $invoice->period_start,
                    'period_end' => $invoice->period_end,
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => $formattedInvoices
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch invoices',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function invoice(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            $invoice = $user->findInvoice($id);
            
            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $invoice->id,
                    'total' => $invoice->total / 100,
                    'currency' => strtoupper($invoice->currency),
                    'status' => $invoice->status,
                    'paid' => $invoice->paid,
                    'invoice_pdf' => $invoice->invoice_pdf,
                    'created' => $invoice->created,
                    'period_start' => $invoice->period_start,
                    'period_end' => $invoice->period_end,
                    'lines' => $invoice->lines->data,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch invoice',
                'error' => $e->getMessage()
            ], 500);
        }
    }


}
