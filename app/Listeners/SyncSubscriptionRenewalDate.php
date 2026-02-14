<?php

namespace App\Listeners;

use Laravel\Cashier\Events\WebhookHandled;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncSubscriptionRenewalDate
{
    public function handle(WebhookHandled $event): void
    {
        $payload = $event->payload;

        $eventType = $payload['type'] ?? null;

        if (! $eventType) {
            return;
        }

        if (! in_array($eventType, [
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted',
        ])) {
            return;
        }

        $stripeSubscription = $payload['data']['object'] ?? null;

        if (! $stripeSubscription || empty($stripeSubscription['id'])) {
           // Log::warning('Stripe webhook missing subscription object.');
            return;
        }

        $localSubscription = Subscription::where(
            'stripe_id',
            $stripeSubscription['id']
        )->first();

        if (! $localSubscription) {
            Log::info('Subscription not found locally.', [
                'stripe_id' => $stripeSubscription['id'],
            ]);
            return;
        }

        $currentPeriodEnd = isset($stripeSubscription['current_period_end'])
            ? Carbon::createFromTimestamp($stripeSubscription['current_period_end'])
            : null;

        $status = $stripeSubscription['status'] ?? null;

        $needsUpdate =
            $localSubscription->current_period_end != $currentPeriodEnd ||
            $localSubscription->stripe_status !== $status;

        if (! $needsUpdate) {
            return;
        }

        $localSubscription->update([
            'current_period_end' => $currentPeriodEnd,
            'stripe_status'      => $status,
        ]);

        // Log::info('Subscription synced from Stripe.', [
        //     'stripe_id' => $stripeSubscription['id'],
        //     'status' => $status,
        //     'current_period_end' => $currentPeriodEnd,
        // ]);
    }
}
