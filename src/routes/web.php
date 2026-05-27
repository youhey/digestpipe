<?php

use App\Http\Controllers\Auth\GoogleOAuthController;
use App\Http\Controllers\Auth\LocalAdminLoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response('Not Found', 404)
        ->header('Content-Type', 'text/plain; charset=UTF-8')
        ->header('Cache-Control', 'public, max-age=3600, s-maxage=86400');
});

Route::get('/auth/google/redirect', [GoogleOAuthController::class, 'redirect'])
    ->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleOAuthController::class, 'callback'])
    ->name('auth.google.callback');

Route::get('/_local/admin/login', LocalAdminLoginController::class)
    ->name('local.admin.login');
