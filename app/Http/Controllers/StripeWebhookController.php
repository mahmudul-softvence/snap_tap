<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;

class StripeWebhookController extends CashierController
{
    /**
     * Fired when an invoice is successfully paid
     * This is the CORRECT place to mark trial → paid
     */
    protected function handleInvoicePaymentSucceeded(array $payload)
    {
        $invoice = $payload['data']['object'];

        // Invoice must belong to a subscription
        if (empty($invoice['subscription'])) {
            return $this->successMethod();
        }

        $subscription = \Laravel\Cashier\Subscription::where(
            'stripe_id',
            $invoice['subscription']
        )->first();

        if (! $subscription) {
            return $this->successMethod();
        }

        /**
         * First paid invoice after trial
         * Stripe sends billing_reason:
         * - subscription_create (trial ended early / skipTrial)
         * - subscription_cycle (natural trial end)
         */
        if (
            in_array($invoice['billing_reason'], ['subscription_create', 'subscription_cycle']) &&
            ! $subscription->trial_converted
        ) {
            $subscription->update([
                'trial_converted' => true,
                'trial_ended_at'  => now(),
                'stripe_status'   => 'active',
                'trial_metadata->paid_invoice_id' => $invoice['id'],
            ]);

            Log::info('Trial converted to paid', [
                'subscription_id' => $subscription->id,
                'invoice_id' => $invoice['id'],
            ]);

            // $subscription->owner?->notify(
            //     new \App\Notifications\TrialConverted($invoice)
            // );
        }

        return $this->successMethod();
    }

    /**
     * Fired whenever a subscription changes (status, trial_end, cancel, etc.)
     */
    protected function handleCustomerSubscriptionUpdated(array $payload)
    {
        $stripeSubscription = $payload['data']['object'];

        $subscription = \Laravel\Cashier\Subscription::where(
            'stripe_id',
            $stripeSubscription['id']
        )->first();

        if (! $subscription) {
            return $this->successMethod();
        }

        $trialEnd = $stripeSubscription['trial_end']
            ? Carbon::createFromTimestamp($stripeSubscription['trial_end'])
            : null;

        $subscription->update([
            'stripe_status' => $stripeSubscription['status'],
            'trial_ends_at' => $trialEnd,
            'ends_at'       => isset($stripeSubscription['ended_at'])
                ? Carbon::createFromTimestamp($stripeSubscription['ended_at'])
                : null,
        ]);

        return $this->successMethod();
    }

    /**
     * Fired when Stripe creates an invoice (trial ending reminder)
     */
    protected function handleInvoiceCreated(array $payload)
    {
        $invoice = $payload['data']['object'];

        if (
            $invoice['billing_reason'] !== 'subscription_cycle' ||
            $invoice['amount_due'] <= 0
        ) {
            return $this->successMethod();
        }

        $subscription = \Laravel\Cashier\Subscription::where(
            'stripe_id',
            $invoice['subscription']
        )->first();

        if (! $subscription) {
            return $this->successMethod();
        }

        // $subscription->owner?->notify(
        //     new \App\Notifications\TrialEnding($invoice)
        // );

        Log::info('Trial ending notification sent', [
            'subscription_id' => $subscription->id,
        ]);

        return $this->successMethod();
    }
}
