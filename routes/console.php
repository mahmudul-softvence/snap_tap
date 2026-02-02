<?php

use Illuminate\Support\Facades\Schedule;


Schedule::command('reviews:sync')->everyMinute();

Schedule::command('disposable:update')->daily();
