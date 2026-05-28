<?php

namespace App\Providers;

use App\Analysis\ArticleAnalyzer;
use App\Analysis\FakeArticleAnalyzer;
use App\Analysis\OpenAiArticleAnalyzer;
use App\Translation\DeepLTranslationClient;
use App\Translation\FakeTranslationClient;
use App\Translation\NullTranslationClient;
use App\Translation\TranslationClient;
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

        $this->app->bind(TranslationClient::class, function (): TranslationClient {
            $configuredDriver = config('digestpipe.translation.driver', 'none');
            $driver = is_string($configuredDriver) ? $configuredDriver : 'none';

            return match ($driver) {
                'none' => new NullTranslationClient(),
                'fake' => new FakeTranslationClient(),
                'deepl' => new DeepLTranslationClient(),
                default => throw new InvalidArgumentException("Unsupported digestpipe translation driver [{$driver}]."),
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
