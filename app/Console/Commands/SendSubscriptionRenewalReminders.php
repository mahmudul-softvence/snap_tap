<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\Subscription;
use App\Notifications\SubscriptionRenewalReminderNotification;

class SendSubscriptionRenewalReminders extends Command
{
    protected $signature = 'subscriptions:send-renewal-reminders';
    protected $description = 'Send subscription renewal reminder notifications';

    public function handle(): int
    {
        Log::info('Subscription renewal reminder command started.');

        $startDate = now('UTC')->addDays(30)->startOfDay();
        $endDate   = now('UTC')->addDays(30)->endOfDay();

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

                Log::info('Chunk size: ' . $subscriptions->count());

                foreach ($subscriptions as $subscription) {

                    $user = $subscription->user;

                    if (!$user) {
                        continue;
                    }

                    if (!$user->basicSetting) {
                        continue;
                    }

                    if (!$user->basicSetting->renewel_reminder) {
                        continue;
                    }

                    $user->notify(
                        new SubscriptionRenewalReminderNotification($subscription)
                    );
                }
            });

        Log::info('Subscription renewal reminder command finished.');

        return Command::SUCCESS;
    }
}
