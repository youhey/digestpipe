<?php

use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\ArticleRatingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'abilities:digests:read'])->group(function (): void {
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::get('/articles/{id}', [ArticleController::class, 'show'])
        ->whereNumber('id');
});

Route::middleware(['auth:sanctum', 'abilities:digests:rate'])->group(function (): void {
    Route::put('/articles/{id}/rating', [ArticleRatingController::class, 'update'])
        ->whereNumber('id');
    Route::delete('/articles/{id}/rating', [ArticleRatingController::class, 'destroy'])
        ->whereNumber('id');
});
