<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('digestpipe:feeds:fetch')
    ->everyTenMinutes()
    ->withoutOverlapping(15);

Schedule::command('digestpipe:items:enqueue-processing --limit=100')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);
