<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('digestpipe:feeds:fetch')
    ->everyTenMinutes()
    ->withoutOverlapping(15);

Schedule::command('digestpipe:items:enqueue-processing --limit=100 --per-source-limit=10')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);
