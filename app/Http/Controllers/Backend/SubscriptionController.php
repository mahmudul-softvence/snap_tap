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


use Stripe\Invoice;
use Laravel\Cashier\Subscription;

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
                    'canceled' => $subscription->canceled(),
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
            
            if ($user->subscribed('default')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an active subscription'
                ], 400);
            }

            if (!$user->hasStripeId()) {
               $user->createAsStripeCustomer();
            }

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
            
            $user->updateDefaultPaymentMethod($request->payment_method);
            
            $subscription = $user->newSubscription('default', $plan->stripe_price_id);
            
            if ($plan->trial_days > 0) {
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

    public function startFreeTrial(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'payment_method_id' => 'required|string',
        ]);
        
        try {
            $user = $request->user();
            $plan = Plan::findOrFail($request->plan_id);
            
            
            if (!$plan->hasTrial()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This plan does not offer a free trial'
                ], 400);
            }
            
            if ($user->subscribed('default')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an active subscription'
                ], 400);
            }
            
            if (!$user->hasStripeId()) {
                $user->createAsStripeCustomer();
            }
            
            if ($plan->hasSetupFee()) {
                return $this->processTrialWithSetupFee($user, $plan, $request->payment_method_id);
            }
            
            // For completely free trial (no charge at all)
            return $this->processFreeTrial($user, $plan, $request->payment_method_id);
            
        } catch (\Exception $e) {
            Log::error('Start free trial error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to start free trial',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process completely free trial
     */
    private function processFreeTrial($user, $plan, $paymentMethodId)
    {
        // Create SetupIntent to validate card without charging
        $setupIntent = $user->createSetupIntent([
            'payment_method' => $paymentMethodId,
            'confirm' => true,
            'usage' => 'off_session',

            'automatic_payment_methods' => [
                'enabled' => true,
                'allow_redirects' => 'never',
            ],

            'metadata' => [
                'plan_id' => $plan->id,
                'trial_type' => 'free',
            ]
        ]);
        
        if ($setupIntent->status === 'requires_action') {
            return response()->json([
                'success' => true,
                'requires_action' => true,
                'flow' => 'free_trial',
                'message' => 'Card verification required',
                'data' => [
                    'client_secret' => $setupIntent->client_secret,
                    'setup_intent_id' => $setupIntent->id,
                ]
            ]);
        }
        
        if ($setupIntent->status !== 'succeeded') {
            throw new \Exception('Card verification failed');
        }
        
        
        $user->addPaymentMethod($paymentMethodId);
        $user->updateDefaultPaymentMethod($paymentMethodId);
        
        
        $subscription = $user->newSubscription('default', $plan->stripe_price_id)
            ->trialDays($plan->trial_days)->create($paymentMethodId);
        
        $subscription->update([
            'trial_type' => 'free',
            'trial_metadata' => [
                'plan_id' => $plan->id,
                'started_at' => now()->toISOString(),
                'ends_at' =>  $subscription->trial_ends_at?->toISOString()
            ]
        ]);

        // $trialEndTime = now()->addMinutes(5);

        // // 2. Pass the variable into both the trial logic and the metadata
        // $subscription = $user->newSubscription('default', $plan->stripe_price_id)
        //     ->trialUntil($trialEndTime)
        //     ->withMetadata([
        //         'plan_id'    => $plan->id,
        //         'started_at' => now()->toISOString(),
        //         'trial_type' => 'free', 
        //     ])
        // ->create($paymentMethodId);

        return response()->json([
            'success' => true,
            'message' => 'Free trial started successfully!',
            'data' => [
                'subscription' => $subscription,
                'trial' => [
                    'type' => 'free',
                    'days' => $plan->trial_days,
                    'start_date' => now()->format('Y-m-d'),
                    'end_date' => now()->addDays($plan->trial_days)->format('Y-m-d'),
                    'next_payment_date' => now()->addDays($plan->trial_days)->format('Y-m-d'),
                    'amount_charged' => 0,
                ],
                'payment_method_saved' => true,
            ]
        ]);
    }
    
    /**
     * Process trial with setup fee
     */
    private function processTrialWithSetupFee($user, $plan, $paymentMethodId)
    {
        // Charge setup fee immediately
        $setupFeeInCents = $plan->setup_fee * 100;
        
        // Create PaymentIntent for setup fee
        $paymentIntent = PaymentIntent::create([
            'amount' => $setupFeeInCents,
            'currency' => strtolower($plan->currency),
            'customer' => $user->stripe_id,
            'payment_method' => $paymentMethodId,
            'off_session' => false,
            'confirm' => true,
            'description' => "Setup fee for {$plan->name} trial",
            'metadata' => [
                'plan_id' => $plan->id,
                'type' => 'trial_setup_fee',
                'trial_days' => $plan->trial_days,
            ]
        ]);
        
        // Handle 3D Secure if required
        if ($paymentIntent->status === 'requires_action') {
            return response()->json([
                'success' => true,
                'requires_action' => true,
                'flow' => 'trial_with_setup_fee',
                'message' => 'Payment authentication required for setup fee',
                'data' => [
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                    'amount' => $plan->setup_fee,
                    'currency' => $plan->currency,
                ]
            ]);
        }
        
        if ($paymentIntent->status !== 'succeeded') {
            throw new \Exception('Setup fee payment failed');
        }
        
        // Payment succeeded, attach payment method
        $user->addPaymentMethod($paymentMethodId);
        $user->updateDefaultPaymentMethod($paymentMethodId);
        
        // Create subscription with trial
        $subscription = $user->newSubscription('default', $plan->stripe_price_id)
            ->trialDays($plan->trial_days)
            ->create(null, [
                'payment_behavior' => 'default_incomplete',
                'expand' => ['latest_invoice.payment_intent']
            ]);
        
        // Save trial metadata with setup fee info
        $dbSubscription = $user->subscription('default');
        $dbSubscription->update([
            'trial_type' => 'setup_fee',
            'trial_amount_paid' => $plan->setup_fee,
            'trial_started_at' => now(),
            'trial_ended_at' => now()->addDays($plan->trial_days),
            'trial_metadata' => [
                'plan_id' => $plan->id,
                'setup_fee_paid' => $plan->setup_fee,
                'setup_fee_payment_intent' => $paymentIntent->id,
                'started_at' => now()->toISOString(),
                'ends_at' => now()->addDays($plan->trial_days)->toISOString(),
            ]
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Trial started with setup fee!',
            'data' => [
                'subscription' => $subscription,
                'plan' => $plan,
                'trial' => [
                    'type' => 'setup_fee',
                    'days' => $plan->trial_days,
                    'setup_fee_paid' => $plan->setup_fee,
                    'setup_fee_payment_id' => $paymentIntent->id,
                    'start_date' => now()->format('Y-m-d'),
                    'end_date' => now()->addDays($plan->trial_days)->format('Y-m-d'),
                    'next_payment_date' => now()->addDays($plan->trial_days)->format('Y-m-d'),
                    'next_payment_amount' => $plan->price,
                ],
                'receipt' => [
                    'amount' => $plan->setup_fee,
                    'currency' => $plan->currency,
                    'receipt_url' => $paymentIntent->charges->data[0]->receipt_url ?? null,
                ]
            ]
        ]);
    }

    public function convertTrialToPaid(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->subscribed('default')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active subscription found',
                ], 404);
            }

            $subscription = $user->subscription('default');

            if (!$subscription->onTrial()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription is not in trial period',
                ], 400);
            }

            $subscription->skipTrial();

            // Refresh local data   
            $subscription->refresh();

            $subscription->update([
                'trial_ends_at' => now(),
                'trial_converted' => true,
                'trial_metadata->converted_at' => now()->toISOString(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Trial converted to paid subscription',
                'data' => [
                    'subscription' => $subscription,
                    'next_billing_date' => $subscription->asStripeSubscription()->current_period_end,
                ],
            ]);

        } catch (\Stripe\Exception\CardException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment failed',
                'error' => $e->getMessage(),
            ], 402);

        } catch (\Exception $e) {
            Log::error('Convert trial error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to convert trial',
                'error' => $e->getMessage(),
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
