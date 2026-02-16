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

       $startDate = now('UTC')
            ->addMonth()
            ->subDay()
            ->startOfDay();

        $endDate = now('UTC')
            ->addMonth()
            ->addDay()
            ->endOfDay();

        Subscription::query()
            ->where(function ($query) use ($startDate, $endDate) {

                $query->where(function ($q) use ($startDate, $endDate) {
                    $q->where('stripe_status', 'active')
                      ->whereBetween('current_period_end', [$startDate, $endDate]);
                })

                ->orWhere(function ($q) use ($startDate, $endDate) {
                    $q->where('stripe_status', 'trialing')
                      ->whereNotNull('trial_ends_at')
                      ->whereBetween('trial_ends_at', [$startDate, $endDate]);
                });

            })
            ->with(['user.basicSetting', 'plan'])
            ->chunkById(100, function ($subscriptions) {

                foreach ($subscriptions as $subscription) {
                        Log::info("Processing subscription ID: {$subscription->id}");
                    $user = $subscription->user;

                    if (!$user) {
                        Log::info("No user found");
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
