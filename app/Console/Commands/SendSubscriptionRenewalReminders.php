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

        //$targetDate = now()->addDays(7)->toDateString();
        $targetDate = now()->toDateString();

        Subscription::query()
            ->whereDate('ends_at', $targetDate)
            ->whereIn('stripe_status', ['active', 'trialing', 'past_due'])
            ->with(['user.basicSetting', 'plan'])
            ->chunkById(100, function ($subscriptions) {

                Log::info('Found subscriptions: ' . $subscriptions->count());

                foreach ($subscriptions as $subscription) {

                    $user = $subscription->user;

                    if (!$user) {
                        continue;
                    }

                    if (!$user->basicSetting?->renewel_reminder) {
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
