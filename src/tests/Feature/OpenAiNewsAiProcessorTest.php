<?php

namespace Tests\Feature;

use App\Jobs\SummarizeNewsItemJob;
use App\Jobs\TranslateNewsItemJob;
use App\Models\NewsItem;
use App\Processing\AiProcessingException;
use App\Processing\FakeNewsAiProcessor;
use App\Processing\NewsAiProcessor;
use App\Processing\OpenAiNewsAiProcessor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * @internal
 */
class OpenAiNewsAiProcessorTest extends TestCase
{
    use RefreshDatabase;

    public function testFakeDriverStillWorks(): void
    {
        $processor = new FakeNewsAiProcessor();
        $item = $this->createNewsItem([
            'title' => 'Original title',
            'excerpt' => 'Original excerpt',
        ]);

        $result = $processor->translate($item);

        self::assertSame('[ja] Original title', $result->title);
    }

    public function testOpenAiDriverCanBeResolvedFromConfig(): void
    {
        config(['digestpipe.ai.driver' => 'openai']);

        self::assertInstanceOf(OpenAiNewsAiProcessor::class, $this->app->make(NewsAiProcessor::class));
    }

    public function testMissingApiKeyFailsClearly(): void
    {
        config([
            'digestpipe.ai.driver' => 'openai',
            'digestpipe.openai.api_key' => null,
        ]);

        $this->expectException(AiProcessingException::class);
        $this->expectExceptionMessage('OpenAI API key is not configured.');

        $this->app->make(NewsAiProcessor::class)->translate($this->createNewsItem());
    }

    public function testTranslationJobCanUseConfiguredOpenAiService(): void
    {
        $this->configureOpenAi();
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response($this->openAiOutput([
                'translated_title' => '翻訳タイトル',
                'translated_description' => '翻訳本文',
            ]), 200),
        ]);

        $item = $this->createNewsItem([
            'translation_status' => 'queued',
        ]);

        (new TranslateNewsItemJob($item->id))->handle($this->app->make(NewsAiProcessor::class));

        $this->assertDatabaseHas('news_items', [
            'id' => $item->id,
            'translation_status' => 'completed',
            'translated_title' => '翻訳タイトル',
            'translated_description' => '翻訳本文',
        ]);
    }

    public function testSummaryJobCanUseConfiguredOpenAiService(): void
    {
        $this->configureOpenAi();
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response($this->openAiOutput([
                'summary' => '短い要約です。',
            ]), 200),
        ]);

        $item = $this->createNewsItem([
            'translation_status' => 'completed',
            'summary_status' => 'queued',
            'translated_title' => '翻訳タイトル',
            'translated_description' => '翻訳本文',
        ]);

        (new SummarizeNewsItemJob($item->id))->handle($this->app->make(NewsAiProcessor::class));

        $this->assertDatabaseHas('news_items', [
            'id' => $item->id,
            'summary_status' => 'completed',
            'summary' => '短い要約です。',
        ]);
    }

    public function testOpenAiServiceSendsResponsesApiStructuredOutputRequest(): void
    {
        $this->configureOpenAi();
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response($this->openAiOutput([
                'translated_title' => '翻訳タイトル',
                'translated_description' => '翻訳本文',
            ]), 200),
        ]);

        $this->app->make(NewsAiProcessor::class)->translate($this->createNewsItem());

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $text = $payload['text'] ?? null;
            $format = is_array($text) ? ($text['format'] ?? null) : null;

            if (! is_array($format)) {
                return false;
            }

            return $request->url() === 'https://api.openai.com/v1/responses'
                && $request->hasHeader('Authorization', 'Bearer test-openai-key')
                && ($payload['model'] ?? null) === 'gpt-test'
                && ($payload['store'] ?? null) === false
                && ($format['type'] ?? null) === 'json_schema'
                && ($format['name'] ?? null) === 'digestpipe_translation';
        });
    }

    public function testOpenAiServiceHandlesConnectionFailure(): void
    {
        $this->configureOpenAi();
        Http::fake(static function (): never {
            throw new ConnectionException('Connection timed out.');
        });

        $this->expectException(AiProcessingException::class);
        $this->expectExceptionMessage('OpenAI request failed: connection error.');

        $this->app->make(NewsAiProcessor::class)->translate($this->createNewsItem());
    }

    public function testOpenAiServiceHandlesHttpErrorResponse(): void
    {
        $this->configureOpenAi();
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'error' => [
                    'message' => 'The model is not available.',
                ],
            ], 404),
        ]);

        $this->expectException(AiProcessingException::class);
        $this->expectExceptionMessage('OpenAI request failed: The model is not available.');

        $this->app->make(NewsAiProcessor::class)->translate($this->createNewsItem());
    }

    public function testOpenAiServiceHandlesMalformedResponse(): void
    {
        $this->configureOpenAi();
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output_text' => 'not json',
            ], 200),
        ]);

        $this->expectException(AiProcessingException::class);
        $this->expectExceptionMessage('OpenAI response was not valid JSON.');

        $this->app->make(NewsAiProcessor::class)->translate($this->createNewsItem());
    }

    public function testSummaryJobMarksItemAsFailedOnOpenAiException(): void
    {
        $this->configureOpenAi();
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output_text' => '{}',
            ], 200),
        ]);

        $item = $this->createNewsItem([
            'translation_status' => 'completed',
            'summary_status' => 'queued',
            'translated_title' => '翻訳タイトル',
        ]);

        (new SummarizeNewsItemJob($item->id))->handle($this->app->make(NewsAiProcessor::class));

        $this->assertDatabaseHas('news_items', [
            'id' => $item->id,
            'summary_status' => 'failed',
            'processing_error' => 'OpenAI summary response did not include summary.',
        ]);
    }

    private function configureOpenAi(): void
    {
        config([
            'digestpipe.ai.driver' => 'openai',
            'digestpipe.openai.api_key' => 'test-openai-key',
            'digestpipe.openai.model' => 'gpt-test',
            'digestpipe.openai.request_timeout' => 5,
            'digestpipe.openai.max_retries' => 0,
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
                'input_tokens' => 10,
                'output_tokens' => 8,
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
            'external_id' => 'external-openai',
            'identity_hash' => hash('sha256', 'external-openai' . serialize($attributes)),
            'source_url' => 'https://news.example.test/openai',
            'title' => 'Example title',
            'excerpt' => 'Example excerpt',
            'published_at' => CarbonImmutable::parse('2026-05-23 12:00:00'),
            'fetched_at' => CarbonImmutable::parse('2026-05-23 12:05:00'),
            'content_hash' => hash('sha256', 'content-openai' . serialize($attributes)),
            'processing_status' => 'fetched',
            'translation_status' => 'pending',
            'summary_status' => 'pending',
            'error_message' => null,
            'processing_error' => null,
        ], $attributes));
    }
}
