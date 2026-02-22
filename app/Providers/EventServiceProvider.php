<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Laravel\Cashier\Events\WebhookHandled;
use App\Listeners\SyncSubscriptionRenewalDate;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        WebhookHandled::class => [
            SyncSubscriptionRenewalDate::class,
        ],
    ];
}