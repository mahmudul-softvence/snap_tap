<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Models\Subscription;

class SubscriptionRenewalReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $renewalDate;

    public function __construct(
        protected Subscription $subscription
    ) {
        $this->renewalDate = $this->resolveRenewalDate();
    }

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
                $this->renewalDate
            ),
            'subscription_id' => $this->subscription->id,
            'plan_name' => $plan?->name,
            'renewal_date' => $this->renewalDate,
        ];
    }

    protected function resolveRenewalDate(): string
    {
        if ($this->subscription->stripe_status === 'trialing') {
            return optional($this->subscription->trial_ends_at)->toDateString();
        }

        return optional($this->subscription->current_period_end)->toDateString();
    }
}
