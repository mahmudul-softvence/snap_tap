<?php

use Illuminate\Support\Facades\Schedule;


Schedule::command('reviews:sync')->everyMinute();

Schedule::command('disposable:update')->daily();

// Schedule::command('subscriptions:send-renewal-reminders')
//     ->everyMinute();
//     ->dailyAt('09:00');