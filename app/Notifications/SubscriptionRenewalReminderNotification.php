<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Models\Subscription;

class SubscriptionRenewalReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Subscription $subscription
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $plan = $this->subscription->plan;

        return [
            'type' => 'subscription_renewal_reminder',
            'title' => 'Subscription Renewal Reminder',
            'message' => sprintf(
                'Your %s plan will renew on %s.',
                $plan?->name ?? 'subscription',
                optional($this->subscription->ends_at)->toDateString()
            ),
            'subscription_id' => $this->subscription->id,
            'plan_name' => $plan?->name,
            'renewal_date' => optional($this->subscription->ends_at)->toDateString(),
        ];
    }
}
