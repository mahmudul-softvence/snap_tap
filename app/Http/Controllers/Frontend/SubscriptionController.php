<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Plan;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Stripe\Stripe;
use Stripe\SetupIntent;
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
                'name' => $getPlan,
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

    public function createSetupIntent(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        try {
            $user = $request->user();
            $plan = Plan::findOrFail($request->plan_id);

            if (! $plan->hasTrial()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This plan does not support free trials',
                ], 400);
            }

            if ($user->subscribed('default')) {
                return response()->json([
                    'success' => false,
                    'message' => 'User already has a subscription',
                ], 400);
            }

            if (! $user->hasStripeId()) {
                $user->createAsStripeCustomer();
            }

            $setupIntent = $user->createSetupIntent([
                'usage' => 'off_session',
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never',
                ],
                'metadata' => [
                    'purpose' => 'free_trial',
                    'plan_id' => $plan->id,
                    'user_id' => $user->id,
                ],
            ]);

            return response()->json([
                'success' => true,
                'client_secret' => $setupIntent->client_secret,
                'setup_intent_id' => $setupIntent->id,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize trial',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function startFreeTrial(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'setup_intent_id' => 'required|string',
            'auto_renew' => 'required|boolean',
        ]);

        try {
            $user = $request->user();
            $plan = Plan::findOrFail($request->plan_id);
            
            if ($user->subscribed('default')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Already subscribed',
                ], 400);
            }

            return $this->startTrial(
                $user,
                $plan,
                $request->setup_intent_id,
                $request->auto_renew
            );

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process purchase',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function startTrial($user, Plan $plan, string $setup_intent_id, bool $autoRenew): JsonResponse
    {
        try {
                Stripe::setApiKey(config('cashier.secret'));
                $setupIntent = SetupIntent::retrieve($setup_intent_id);

                if ($setupIntent->customer !== $user->stripe_id) {
                    throw new \Exception('Invalid SetupIntent owner');
                }

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
            $paymentMethodId = $setupIntent->payment_method;

            if (! collect($user->paymentMethods())->contains('id', $paymentMethodId)) {
                $user->addPaymentMethod($paymentMethodId);
            }

            $user->updateDefaultPaymentMethod($paymentMethodId);

            $subscription = $user
                ->newSubscription('default', $plan->stripe_price_id)
                ->trialDays($plan->trial_days)
                ->create(null, [
                        'metadata' => [
                            'plan_id'         => (string) $plan->id,
                            'setup_intent_id' => $setup_intent_id,
                            'flow'            => 'free_trial',
                            'auto_renew' => $autoRenew ? 'yes' : 'no' 
                        ],
                    ]);

            return response()->json([
                'success' => true,
                'message' => 'Free trial started successfully',
                'data' => [
                    'subscription_id' => $subscription->id,
                    'stripe_subscription_id' => $subscription->stripe_id,
                    'trial_ends_at' => $subscription->trial_ends_at,
                    'auto_renew' => true,
                    'amount_charged_now' => 0,
                ],
            ]);

        } catch (ApiErrorException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe error',
                'error' => $e->getMessage(),
            ], 500);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start trial',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function createPaymentIntent(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $user = $request->user();
        $plan = Plan::findOrFail($request->plan_id);

        if (! $user->hasStripeId()) {
            $user->createAsStripeCustomer();
        }

        Stripe::setApiKey(config('cashier.secret'));

        $paymentIntent = PaymentIntent::create([
            'amount' => (int) ($plan->price * 100),
            'currency' => strtolower($plan->currency ?? 'usd'),
            'customer' => $user->stripe_id,
            'automatic_payment_methods' => [
                'enabled' => true,
                'allow_redirects' => 'never',
            ],
            'setup_future_usage' => 'off_session',
            'metadata' => [
                'purpose' => 'subscription_initial_payment',
                'plan_id' => $plan->id,
                'user_id' => $user->id,
            ],
        ]);

        return response()->json([
            'success' => true,
            'client_secret' => $paymentIntent->client_secret,
            'payment_intent_id' => $paymentIntent->id,
        ]);
    }

    public function buyNow(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'payment_intent_id' => 'required|string',
            'auto_renew' => 'required|boolean',
        ]);

        try {
            $user = $request->user();
            $plan = Plan::findOrFail($request->plan_id);

            if ($user->subscribed('default')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an active subscription',
                ], 400);
            }

            return $this->processImmediatePurchase(
                $user,
                $plan,
                $request->payment_intent_id,
                $request->auto_renew
            );

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process purchase',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    private function processImmediatePurchase($user, Plan $plan, string $paymentIntentId, bool $autoRenew): JsonResponse
    {
        try {
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
                        'payment_intent_id' => $paymentIntentId,
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
                    'current_period_end' => $subscription->currentPeriodEnd()?->toDateTimeString(),
                ],
            ]);

        } catch (ApiErrorException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe error occurred',
                'error' => $e->getMessage(),
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function convertTrialToPaid(Request $request): JsonResponse
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

    public function billingHistory(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $subscriptions = $user->subscriptions()
                ->orderByDesc('created_at')
                ->paginate(
                    $request->get('per_page', 10)
                );

            return response()->json([
                'success' => true,
                'message' => 'Subscriptions fetched successfully',
                'data' => $subscriptions->through(function ($subscription) {
                 $plan = Plan::where('stripe_price_id', $subscription->stripe_price)->first();

                    return [
                        'id' => $subscription->id,
                        'plan_name' => $plan?->name,
                        'amount' => $plan->price,
                        'start_date' => $subscription->created_at,
                        'end_date' => $subscription->ends_at,
                    ];
                }),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch billing history',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
