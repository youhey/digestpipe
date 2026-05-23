<?php

namespace Tests\Feature;

use App\Jobs\SummarizeNewsItemJob;
use App\Jobs\TranslateNewsItemJob;
use App\Models\NewsItem;
use App\Processing\NewsAiProcessor;
use App\Processing\NewsSummaryResult;
use App\Processing\NewsTranslationResult;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\PendingCommand;
use RuntimeException;
use Tests\TestCase;

/**
 * @internal
 */
class ProcessingPipelineTest extends TestCase
{
    use RefreshDatabase;

    private int $newsItemSequence = 0;

    public function testEnqueueCommandCanRun(): void
    {
        Queue::fake();
        $item = $this->createNewsItem();

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertPushed(TranslateNewsItemJob::class, static fn (TranslateNewsItemJob $job): bool => $job->newsItemId === $item->id);
    }

    public function testDryRunDoesNotDispatchJobsOrChangeStatuses(): void
    {
        Queue::fake();
        $item = $this->createNewsItem();

        $this->enqueueProcessing(['--dry-run' => true])
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertDatabaseHas('news_items', [
            'id' => $item->id,
            'translation_status' => 'pending',
            'summary_status' => 'pending',
        ]);
    }

    public function testTranslationJobsAreDispatchedForPendingTranslationItems(): void
    {
        Queue::fake();
        $item = $this->createNewsItem();

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertPushed(TranslateNewsItemJob::class, 1);
        Queue::assertPushed(TranslateNewsItemJob::class, static fn (TranslateNewsItemJob $job): bool => $job->newsItemId === $item->id);
        $this->assertDatabaseHas('news_items', [
            'id' => $item->id,
            'translation_status' => 'queued',
        ]);
    }

    public function testSummaryJobsAreDispatchedForTranslatedItemsWithPendingSummary(): void
    {
        Queue::fake();
        $item = $this->createNewsItem([
            'translation_status' => 'completed',
            'summary_status' => 'pending',
            'translated_title' => '[ja] Example title',
            'translated_description' => '[ja] Example excerpt',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertPushed(SummarizeNewsItemJob::class, 1);
        Queue::assertPushed(SummarizeNewsItemJob::class, static fn (SummarizeNewsItemJob $job): bool => $job->newsItemId === $item->id);
        $this->assertDatabaseHas('news_items', [
            'id' => $item->id,
            'summary_status' => 'queued',
        ]);
    }

    public function testOnlyTranslationDispatchesOnlyTranslationJobs(): void
    {
        Queue::fake();
        $this->createNewsItem();
        $this->createNewsItem([
            'translation_status' => 'completed',
            'translated_title' => '[ja] Completed title',
        ]);

        $this->enqueueProcessing(['--only' => 'translation'])
            ->assertSuccessful();

        Queue::assertPushed(TranslateNewsItemJob::class, 1);
        Queue::assertNotPushed(SummarizeNewsItemJob::class);
    }

    public function testOnlySummaryDispatchesOnlySummaryJobs(): void
    {
        Queue::fake();
        $this->createNewsItem();
        $this->createNewsItem([
            'translation_status' => 'completed',
            'translated_title' => '[ja] Completed title',
        ]);

        $this->enqueueProcessing(['--only' => 'summary'])
            ->assertSuccessful();

        Queue::assertNotPushed(TranslateNewsItemJob::class);
        Queue::assertPushed(SummarizeNewsItemJob::class, 1);
    }

    public function testTranslateNewsItemJobUpdatesTranslationFieldsAndStatus(): void
    {
        $item = $this->createNewsItem([
            'title' => 'Original title',
            'excerpt' => 'Original excerpt',
            'translation_status' => 'queued',
        ]);

        (new TranslateNewsItemJob($item->id))->handle($this->app->make(NewsAiProcessor::class));

        $this->assertDatabaseHas('news_items', [
            'id' => $item->id,
            'translation_status' => 'completed',
            'translated_title' => '[ja] Original title',
            'translated_description' => '[ja] Original excerpt',
            'processing_error' => null,
        ]);

        $item->refresh();

        self::assertNotNull($item->translation_started_at);
        self::assertNotNull($item->translation_completed_at);
    }

    public function testSummarizeNewsItemJobUpdatesSummaryFieldsAndStatus(): void
    {
        $item = $this->createNewsItem([
            'translation_status' => 'completed',
            'summary_status' => 'queued',
            'translated_title' => '[ja] Original title',
            'translated_description' => '[ja] Original excerpt',
        ]);

        (new SummarizeNewsItemJob($item->id))->handle($this->app->make(NewsAiProcessor::class));

        $this->assertDatabaseHas('news_items', [
            'id' => $item->id,
            'summary_status' => 'completed',
            'summary' => 'Summary: [ja] Original title [ja] Original excerpt',
            'processing_error' => null,
        ]);

        $item->refresh();

        self::assertNotNull($item->summary_started_at);
        self::assertNotNull($item->summary_completed_at);
    }

    public function testJobsAreIdempotentWhenItemIsAlreadyCompleted(): void
    {
        $item = $this->createNewsItem([
            'translation_status' => 'completed',
            'summary_status' => 'completed',
            'translated_title' => '[ja] Existing title',
            'translated_description' => '[ja] Existing excerpt',
            'summary' => 'Existing summary',
        ]);

        (new TranslateNewsItemJob($item->id))->handle($this->app->make(NewsAiProcessor::class));
        (new SummarizeNewsItemJob($item->id))->handle($this->app->make(NewsAiProcessor::class));

        $this->assertDatabaseHas('news_items', [
            'id' => $item->id,
            'translation_status' => 'completed',
            'summary_status' => 'completed',
            'translated_title' => '[ja] Existing title',
            'translated_description' => '[ja] Existing excerpt',
            'summary' => 'Existing summary',
        ]);
    }

    public function testTranslationFailureMarksItemAsFailedAndStoresError(): void
    {
        $this->app->bind(NewsAiProcessor::class, FailingNewsAiProcessor::class);
        $item = $this->createNewsItem([
            'translation_status' => 'queued',
        ]);

        (new TranslateNewsItemJob($item->id))->handle($this->app->make(NewsAiProcessor::class));

        $this->assertDatabaseHas('news_items', [
            'id' => $item->id,
            'translation_status' => 'failed',
            'processing_error' => 'Stub translation failure.',
        ]);
    }

    /**
     * @param array<string, bool|int|string> $parameters
     */
    private function enqueueProcessing(array $parameters = []): PendingCommand
    {
        $command = $this->artisan('digestpipe:items:enqueue-processing', $parameters);

        assert($command instanceof PendingCommand);

        return $command;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createNewsItem(array $attributes = []): NewsItem
    {
        ++$this->newsItemSequence;
        $sequence = $this->newsItemSequence;

        return NewsItem::query()->create(array_merge([
            'source_key' => 'example',
            'source_name' => 'Example Source',
            'external_id' => 'external-' . $sequence,
            'identity_hash' => hash('sha256', 'external-' . $sequence),
            'source_url' => 'https://news.example.test/' . $sequence,
            'title' => 'Example title ' . $sequence,
            'excerpt' => 'Example excerpt ' . $sequence,
            'published_at' => CarbonImmutable::parse('2026-05-23 12:00:00'),
            'fetched_at' => CarbonImmutable::parse('2026-05-23 12:05:00'),
            'content_hash' => hash('sha256', 'content-' . $sequence),
            'processing_status' => 'fetched',
            'translation_status' => 'pending',
            'summary_status' => 'pending',
            'error_message' => null,
            'processing_error' => null,
        ], $attributes));
    }
}

/**
 * Failure pathを検証するためのAI processing stubです。
 */
class FailingNewsAiProcessor implements NewsAiProcessor
{
    /**
     * 常に翻訳失敗を発生させます。
     */
    public function translate(NewsItem $item): NewsTranslationResult
    {
        throw new RuntimeException('Stub translation failure.');
    }

    /**
     * 要約側では固定の結果を返します。
     */
    public function summarize(NewsItem $item): NewsSummaryResult
    {
        return new NewsSummaryResult('unused');
    }
}
