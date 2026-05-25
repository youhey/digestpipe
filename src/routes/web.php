<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response('Not Found', 404)
        ->header('Content-Type', 'text/plain; charset=UTF-8')
        ->header('Cache-Control', 'public, max-age=3600, s-maxage=86400');
});
