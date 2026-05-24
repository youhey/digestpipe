<?php

namespace App\Providers;

use App\Analysis\ArticleAnalyzer;
use App\Analysis\FakeArticleAnalyzer;
use App\Analysis\OpenAiArticleAnalyzer;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

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
        $this->app->bind(ArticleAnalyzer::class, function (): ArticleAnalyzer {
            $configuredDriver = config('digestpipe.ai.driver', 'fake');
            $driver = is_string($configuredDriver) ? $configuredDriver : 'fake';

            return match ($driver) {
                'fake' => new FakeArticleAnalyzer(),
                'openai' => new OpenAiArticleAnalyzer(),
                default => throw new InvalidArgumentException("Unsupported digestpipe AI driver [{$driver}]."),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    }
}
