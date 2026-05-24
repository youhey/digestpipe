<?php

namespace App\Providers;

use App\Analysis\ArticleAnalyzer;
use App\Analysis\FakeArticleAnalyzer;
use App\Analysis\OpenAiArticleAnalyzer;
use App\Processing\FakeNewsAiProcessor;
use App\Processing\NewsAiProcessor;
use App\Processing\OpenAiNewsAiProcessor;
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
        $this->app->bind(NewsAiProcessor::class, function (): NewsAiProcessor {
            $configuredDriver = config('digestpipe.ai.driver', 'fake');
            $driver = is_string($configuredDriver) ? $configuredDriver : 'fake';

            return match ($driver) {
                'fake' => new FakeNewsAiProcessor(),
                'openai' => new OpenAiNewsAiProcessor(),
                default => throw new InvalidArgumentException("Unsupported digestpipe AI driver [{$driver}]."),
            };
        });

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
