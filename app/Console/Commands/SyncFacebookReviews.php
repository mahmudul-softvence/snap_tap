<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserBusinessAccount;
use App\Jobs\SyncPageReviewsJob;
use Illuminate\Support\Facades\Log;

class SyncFacebookReviews extends Command
{
    protected $signature = 'reviews:sync';
    protected $description = 'Dispatch Facebook review sync jobs';

    public function handle()
    {
        Log::info('Run hoise');
        UserBusinessAccount::where('status', 'connected')
            ->chunk(50, function ($accounts) {
                foreach ($accounts as $account) {
                    SyncPageReviewsJob::dispatch($account);
                }
            });

        return Command::SUCCESS;
    }
}
