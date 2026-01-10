<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Plan;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Stripe\Stripe;
use Stripe\Exception\CardException;
use Stripe\PaymentIntent;
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

            if (! $plan->hasTrial()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This plan does not offer a free trial',
                ], 400);
            }

            if ($user->subscribed('default')) {
                return response()->json([
                    'success' => false,
                    'message' => 'User already has an active subscription',
                ], 400);
            }

            if (! $user->hasStripeId()) {
                $user->createAsStripeCustomer();
            }
            
            return $this->processFreeTrialWithSetupIntent(
                $user,
                $plan,
                $request->payment_method_id
            );

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start free trial',
            ], 500);
        }
    }

    private function processFreeTrialWithSetupIntent($user, $plan, $paymentMethodId)
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
                'purpose' => 'free_trial',
                'plan_id' => $plan->id,
            ],
        ]);

        if ($setupIntent->status === 'requires_action') {
            return response()->json([
                'success' => true,
                'requires_action' => true,
                'flow' => 'free_trial',
                'data' => [
                    'client_secret' => $setupIntent->client_secret,
                    'setup_intent_id' => $setupIntent->id,
                ],
            ]);
        }

        if ($setupIntent->status !== 'succeeded') {
            throw new \Exception('Card verification failed');
        }

        $user->addPaymentMethod($paymentMethodId);
        $user->updateDefaultPaymentMethod($paymentMethodId);

        $subscription = $user->newSubscription('default', $plan->stripe_price_id)
            ->trialDays($plan->trial_days)
            ->create();

        return response()->json([
            'success' => true,
            'message' => 'Free trial started successfully',
            'data' => [
                'subscription_id' => $subscription->id,
                'trial_ends_at' => $subscription->trial_ends_at,
                'auto_renew' => true,
                'amount_charged_now' => 0,
            ],
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
            return response()->json([
                'success' => false,
                'message' => 'Failed to process purchase',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    private function processImmediatePurchase($user, Plan $plan, string $paymentIntentId, bool $autoRenew)
    {
        try {
            if (! $user->hasStripeId()) {
                $user->createAsStripeCustomer();
            }

            Stripe::setApiKey(config('cashier.secret'));

            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);

            if ($paymentIntent->status !== 'succeeded') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not completed',
                    'status' => $paymentIntent->status,
                ], 400);
            }

            $paymentMethodId = $paymentIntent->payment_method;

            if (! collect($user->paymentMethods())->contains('id', $paymentMethodId)) {
                $user->addPaymentMethod($paymentMethodId);
            }

            $user->updateDefaultPaymentMethod($paymentMethodId);

            $subscription = $user
                ->newSubscription('default', $plan->stripe_price_id)
                ->create($paymentMethodId, [
                    'metadata' => [
                        'plan_id' => $plan->id,
                        'auto_renew' => $autoRenew ? 'yes' : 'no',
                        'payment_intent_id' => $paymentIntent->id,
                    ],
                ]);

            if (! $autoRenew) {
                $subscription->cancelAtPeriodEnd();
            }

            return response()->json([
                'success' => true,
                'message' => 'Subscription activated successfully',
                'data' => [
                    'subscription_id' => $subscription->id,
                    'stripe_subscription_id' => $subscription->stripe_id,
                    'status' => $subscription->stripe_status,
                    'auto_renew' => ! $subscription->cancel_at_period_end,
                    'current_period_end' => optional($subscription->current_period_end)
                        ? $subscription->current_period_end->toDateTimeString()
                        : null,
                ],
            ]);

        } catch (ApiErrorException $e) {
            Log::error('Stripe API error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Stripe error occurred',
            ], 500);

        } catch (\Exception $e) {
            Log::error('Immediate purchase failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process purchase',
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

            return response()->json([
                'success' => false,
                'message' => 'Failed to convert trial',
                'error' => $e->getMessage(),
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

}
