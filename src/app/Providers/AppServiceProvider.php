<?php

namespace App\Providers;

use App\Processing\FakeNewsAiProcessor;
use App\Processing\NewsAiProcessor;
use Illuminate\Support\ServiceProvider;

/**
 * アプリケーション共通serviceの登録と初期化を行います。
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(NewsAiProcessor::class, FakeNewsAiProcessor::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    }
}
