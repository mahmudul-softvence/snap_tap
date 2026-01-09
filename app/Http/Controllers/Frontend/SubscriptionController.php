<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Plan;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\SetupIntent; 
use Stripe\Exception\CardException;
use Stripe\Exception\RateLimitException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;


class SubscriptionController extends Controller
{

    public function show(Request $request): JsonResponse
    {
        
        try {
            $user = $request->user();
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
                'name' => $subscription->type,
                'stripe_status' => $subscription->stripe_status,
                'plan' => $getPlan,
                'price' => $amount,
                'trial_started_at' => $subscription->trial_started_at,
                'start' => $displayStartDate?->format('Y-m-d'),
                'ends' => $displayEndDate?->format('Y-m-d'),
                'renew_on' => $renewOn?->format('Y-m-d'),
                'on_trial' => $subscription->onTrial(),
                'canceled' => $subscription->canceled(),
                'on_grace_period' => $subscription->onGracePeriod(),
                'active' => $subscription->active(),
                'card_info' =>  $formattedMethods,
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

    private function processFreeTrial($user, $plan, $paymentMethodId)
    {
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
            'trial_started_at' => now(),
            'trial_metadata' => [
                'plan_id' => $plan->id,
                'started_at' => now()->toISOString(),
                'ends_at' =>  $subscription->trial_ends_at?->toISOString()
            ]
        ]);

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

    public function buyNow(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'payment_method_id' => 'required|string',
            'auto_renew' => 'required|boolean',
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
            
            return $this->processImmediatePurchase($user, $plan, $request->payment_method_id, $request->auto_renew);
            
        } catch (\Exception $e) {
            Log::error('Buy now error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process purchase',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    private function processImmediatePurchase($user, $plan, $paymentMethodId, bool $autoRenew)
    {
        try {
            if (! collect($user->paymentMethods())->contains('id', $paymentMethodId)) {
                $user->addPaymentMethod($paymentMethodId);
            }

            $user->updateDefaultPaymentMethod($paymentMethodId);

            $subscription = $user
                ->newSubscription('default', $plan->stripe_price_id)
                ->create($paymentMethodId, [
                    'payment_behavior' => 'default_incomplete',
                    'expand' => ['latest_invoice.payment_intent'],
                    'metadata' => [
                        'plan_id' => $plan->id,
                        'auto_renew' => $autoRenew,
                    ],
                ]);

            if (! $autoRenew && $subscription->valid()) {
                $subscription->cancelAtPeriodEnd();
            }

            $stripeSubscription = $subscription->asStripeSubscription();
            $invoice = $stripeSubscription->latest_invoice;
            $paymentIntent = $invoice->payment_intent ?? null;

            if ($paymentIntent && in_array($paymentIntent->status, [
                'requires_action',
                'requires_confirmation',
            ])) {
                return response()->json([
                    'success' => true,
                    'requires_action' => true,
                    'message' => 'Payment authentication required',
                    'data' => [
                        'client_secret' => $paymentIntent->client_secret,
                        'subscription_id' => $subscription->id,
                        'invoice_id' => $invoice->id,
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Subscription activated successfully',
                'data' => [
                    'subscription_id' => $subscription->id,
                    'status' => $subscription->stripe_status,
                    'auto_renew' => ! $subscription->cancel_at_period_end,
                    'current_period_end' => $subscription->ends_at,
                ],
            ]);

        } catch (CardException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getError()->message,
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
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
            $subscription->refresh();

            $subscription->update([
                'trial_ends_at' => now(),
                'trial_converted' => true,
                'trial_metadata->converted_at' => now()->toISOString(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Trial converted to paid subscription',
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


}
