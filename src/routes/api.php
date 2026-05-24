<?php

use App\Http\Controllers\Api\ArticleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'abilities:digests:read'])->group(function (): void {
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::get('/articles/{id}', [ArticleController::class, 'show'])
        ->whereNumber('id');
});
