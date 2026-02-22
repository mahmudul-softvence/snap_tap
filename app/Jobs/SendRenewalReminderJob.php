<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Notifications\SubscriptionRenewalReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendRenewalReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;

    public function handle(): void
    {
        Log::info('Renewal Reminder Job started.');

        $targetDate = now('UTC')->addDays(7)->toDateString();

        Log::info("Target renewal date: {$targetDate}");

        Subscription::query()
            ->where(function ($query) use ($targetDate) {

                $query->where(function ($q) use ($targetDate) {
                    $q->where('stripe_status', 'active')
                    ->whereNotNull('current_period_end')
                    ->whereDate('current_period_end', $targetDate);
                })

                ->orWhere(function ($q) use ($targetDate) {
                    $q->where('stripe_status', 'trialing')
                    ->whereNotNull('trial_ends_at')
                    ->whereDate('trial_ends_at', $targetDate);
                });

            })
            ->with(['user.basicSetting', 'plan'])
            ->chunkById(100, function ($subscriptions) {

                foreach ($subscriptions as $subscription) {
                        Log::info("Processing subscription ID: {$subscription->id}");
                    $user = $subscription->user;

                    if (!$user) {
                        Log::warning("Subscription {$subscription->id} has no user.");
                        continue;
                    }

                    if (!$user->basicSetting?->renewel_reminder) {
                        Log::info("Reminder disabled for user {$user->id}");
                        continue;
                    }

                    Log::info("Sending notification to user {$user->id}");
                    $user->notify(
                        new SubscriptionRenewalReminderNotification($subscription)
                    );
                }
            });

        Log::info('Renewal Reminder Job finished.');
    }
}
