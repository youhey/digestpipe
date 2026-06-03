<?php

use Illuminate\Support\Facades\Schedule;

$command = 'digestpipe:run-cycle --feed-limit=20 --item-limit=50 --max-seconds=600';

// JST 08:00 / 08:30 = UTC 23:00 / 23:30
Schedule::command($command)
    ->cron('0,30 23 * * *')
    ->timezone('UTC')
    ->name('digestpipe:run-cycle:jst-08')
    ->withoutOverlapping(30);

// JST 09:00 - 17:30 = UTC 00:00 - 08:30
Schedule::command($command)
    ->cron('0,30 0-8 * * *')
    ->timezone('UTC')
    ->name('digestpipe:run-cycle:jst-09-17')
    ->withoutOverlapping(30);
