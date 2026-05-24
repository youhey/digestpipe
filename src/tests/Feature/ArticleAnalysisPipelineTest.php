<?php

namespace Tests\Feature;

use App\Analysis\ArticleAnalysisException;
use App\Analysis\ArticleAnalysisResult;
use App\Analysis\ArticleAnalyzer;
use App\Analysis\FakeArticleAnalyzer;
use App\Analysis\OpenAiArticleAnalyzer;
use App\Jobs\AnalyzeNewsItemJob;
use App\Models\NewsItem;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * @internal
 */
class ArticleAnalysisPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function testFakeAnalyzerStoresValidJsonAndCompletionFields(): void
    {
        $item = $this->createNewsItem([
            'article_content_status' => 'completed',
            'article_content_text' => 'This article explains a concrete source-language story for downstream digest JSON.',
            'analysis_status' => 'queued',
        ]);

        (new AnalyzeNewsItemJob($item->id))->handle(new FakeArticleAnalyzer());

        $item->refresh();

        self::assertSame('completed', $item->analysis_status);
        self::assertSame('fake', $item->analysis_model);
        $analysisJson = $item->analysis_json;
        self::assertIsArray($analysisJson);
        $classification = $analysisJson['classification'] ?? null;
        self::assertIsArray($classification);
        self::assertSame('1.0', $analysisJson['schema_version'] ?? null);
        self::assertSame('en', $analysisJson['source_language'] ?? null);
        self::assertSame('news_article', $classification['content_type'] ?? null);
        self::assertNotNull($item->analyzed_at);
        self::assertNull($item->analysis_error);
    }

    public function testAnalysisJobMarksFailedOnProviderException(): void
    {
        $item = $this->createNewsItem([
            'article_content_status' => 'completed',
            'article_content_text' => 'Usable article content.',
            'analysis_status' => 'queued',
        ]);

        (new AnalyzeNewsItemJob($item->id))->handle(new FailingArticleAnalyzer());

        $this->assertDatabaseHas('news_items', [
            'id' => $item->id,
            'analysis_status' => 'failed',
            'analysis_error' => 'Stub analysis failure.',
        ]);
    }

    public function testAnalysisJobSkipsUnusableInput(): void
    {
        $item = $this->createNewsItem([
            'title' => '',
            'excerpt' => null,
            'article_content_status' => 'skipped',
            'article_content_text' => null,
            'analysis_status' => 'queued',
        ]);

        (new AnalyzeNewsItemJob($item->id))->handle(new FakeArticleAnalyzer());

        $this->assertDatabaseHas('news_items', [
            'id' => $item->id,
            'analysis_status' => 'skipped',
            'analysis_error' => 'Article analysis input was not usable.',
        ]);
    }

    public function testOpenAiAnalyzerCanBeResolvedFromConfig(): void
    {
        config(['digestpipe.ai.driver' => 'openai']);

        self::assertInstanceOf(OpenAiArticleAnalyzer::class, $this->app->make(ArticleAnalyzer::class));
    }

    public function testOpenAiAnalyzerSendsStructuredOutputRequestWithoutRawHtml(): void
    {
        $this->configureOpenAi();
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response($this->openAiOutput($this->analysisJson('Short source-language brief.')), 200),
        ]);

        $item = $this->createNewsItem([
            'article_content_status' => 'completed',
            'article_content_text' => '<article><p>Extracted <strong>article</strong> text.</p></article>',
            'excerpt' => 'RSS excerpt',
        ]);

        $this->app->make(ArticleAnalyzer::class)->analyze($item);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $text = $payload['text'] ?? null;
            $format = is_array($text) ? ($text['format'] ?? null) : null;
            $input = $payload['input'] ?? null;
            $instructions = $payload['instructions'] ?? null;

            if (! is_array($format) || ! is_string($input) || ! is_string($instructions)) {
                return false;
            }

            return $request->url() === 'https://api.openai.com/v1/responses'
                && $request->hasHeader('Authorization', 'Bearer test-openai-key')
                && ($payload['model'] ?? null) === 'gpt-analysis-test'
                && ($payload['store'] ?? null) === false
                && ($format['type'] ?? null) === 'json_schema'
                && ($format['name'] ?? null) === 'digestpipe_article_analysis'
                && str_contains($input, 'Extracted article text.')
                && ! str_contains($input, '<article>')
                && ! str_contains($input, 'RSS excerpt')
                && str_contains($instructions, 'Do not translate into Japanese');
        });
    }

    public function testOpenAiAnalyzerRejectsInvalidAnalysisJson(): void
    {
        $this->configureOpenAi();
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response($this->openAiOutput([
                'schema_version' => '1.0',
            ]), 200),
        ]);

        $this->expectException(ArticleAnalysisException::class);
        $this->expectExceptionMessage('Article analysis response [title] was missing or invalid.');

        $this->app->make(ArticleAnalyzer::class)->analyze($this->createNewsItem([
            'article_content_status' => 'completed',
            'article_content_text' => 'Usable article content.',
        ]));
    }

    public function testOpenAiAnalyzerHandlesConnectionFailure(): void
    {
        $this->configureOpenAi();
        Http::fake(static function (): never {
            throw new ConnectionException('Connection timed out.');
        });

        $this->expectException(ArticleAnalysisException::class);
        $this->expectExceptionMessage('OpenAI analysis request failed: connection error.');

        $this->app->make(ArticleAnalyzer::class)->analyze($this->createNewsItem([
            'article_content_status' => 'completed',
            'article_content_text' => 'Usable article content.',
        ]));
    }

    private function configureOpenAi(): void
    {
        config([
            'digestpipe.ai.driver' => 'openai',
            'digestpipe.openai.api_key' => 'test-openai-key',
            'digestpipe.openai.request_timeout' => 5,
            'digestpipe.openai.max_retries' => 0,
            'digestpipe.analysis.model' => 'gpt-analysis-test',
            'digestpipe.analysis.schema_version' => '1.0',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function openAiOutput(array $data): array
    {
        return [
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ],
            ],
            'usage' => [
                'input_tokens' => 20,
                'output_tokens' => 12,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createNewsItem(array $attributes = []): NewsItem
    {
        return NewsItem::query()->create(array_merge([
            'source_key' => 'example',
            'source_name' => 'Example Source',
            'external_id' => 'analysis-external-' . hash('sha256', serialize($attributes)),
            'identity_hash' => hash('sha256', 'analysis-external' . serialize($attributes)),
            'source_url' => 'https://news.example.test/analysis',
            'discussion_url' => null,
            'title' => 'Example analysis title',
            'excerpt' => 'Example analysis excerpt',
            'published_at' => CarbonImmutable::parse('2026-05-23 12:00:00'),
            'fetched_at' => CarbonImmutable::parse('2026-05-23 12:05:00'),
            'content_hash' => hash('sha256', 'analysis-content' . serialize($attributes)),
            'article_content_status' => 'pending',
            'article_content_error' => null,
            'analysis_status' => 'pending',
            'analysis_json' => null,
            'analysis_model' => null,
            'analysis_error' => null,
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function analysisJson(string $brief): array
    {
        return [
            'schema_version' => '1.0',
            'source_language' => 'en',
            'title' => [
                'original' => 'Example analysis title',
                'normalized' => 'Example analysis title',
            ],
            'content' => [
                'brief' => $brief,
                'detailed_summary' => $brief . ' Detailed context for downstream digest processing.',
                'key_points' => ['Concrete point one', 'Concrete point two'],
                'background' => null,
                'why_it_matters' => 'It may affect downstream digest consumers.',
                'limitations' => null,
            ],
            'classification' => [
                'content_type' => 'news_article',
                'topics' => ['technology'],
                'entities' => ['Example Entity'],
                'importance' => 3,
                'confidence' => 0.8,
            ],
        ];
    }
}

/**
 * Failure pathを検証するためのarticle analysis stubです。
 */
class FailingArticleAnalyzer implements ArticleAnalyzer
{
    /**
     * 常にanalysis失敗を発生させます。
     */
    public function analyze(NewsItem $item): ArticleAnalysisResult
    {
        throw new ArticleAnalysisException('Stub analysis failure.');
    }
}
