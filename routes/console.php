<?php

use Illuminate\Support\Facades\Schedule;


Schedule::command('reviews:sync')->everyMinute();

Schedule::command('disposable:update')->daily();

// Renew Remainder 
// Schedule::command('subscriptions:send-renewal-reminders')->dailyAt('09:00');

Schedule::command('subscriptions:send-renewal-reminders')
    ->everyMinute();