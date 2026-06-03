<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('digestpipe:run-cycle --feed-limit=20 --item-limit=50 --max-seconds=600')
    ->everyThirtyMinutes()
    ->timezone('Asia/Tokyo')
    ->between('08:00', '17:59')
    ->withoutOverlapping(30);
