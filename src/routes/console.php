<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('digestpipe:feeds:fetch')
    ->everyTenMinutes()
    ->withoutOverlapping();

Schedule::command('digestpipe:items:enqueue-processing --limit=10')
    ->everyFiveMinutes()
    ->withoutOverlapping();
