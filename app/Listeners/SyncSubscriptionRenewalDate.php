<?php

namespace App\Listeners;

use Laravel\Cashier\Events\WebhookHandled;
use App\Models\Subscription;
use Carbon\Carbon;

class SyncSubscriptionRenewalDate
{
    public function handle(WebhookHandled $event): void
    {
        $payload = $event->payload;

        if (! in_array($payload['type'], [
            'customer.subscription.created',
            'customer.subscription.updated',
        ])) {
            return;
        }

        $stripeSubscription = $payload['data']['object'];

        $subscription = Subscription::where(
            'stripe_id',
            $stripeSubscription['id']
        )->first();

        if (! $subscription) {
            return;
        }

        $subscription->update([
            'current_period_end' => Carbon::createFromTimestamp(
                $stripeSubscription['current_period_end']
            ),
            'stripe_status' => $stripeSubscription['status'],
        ]);
    }
}
