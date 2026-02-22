<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\SendRenewalReminderJob;

class SendSubscriptionRenewalReminders extends Command
{
    protected $signature = 'subscriptions:dispatch-renewal-reminders';
    protected $description = 'Dispatch renewal reminder job';

    public function handle(): int
    {
        SendRenewalReminderJob::dispatch();

        $this->info('Renewal reminder job dispatched.');

        return Command::SUCCESS;
    }
}
