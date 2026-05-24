<?php

namespace Tests\Feature;

use App\Jobs\FetchNewsItemArticleContentJob;
use App\Jobs\SummarizeNewsItemJob;
use App\Jobs\TranslateNewsItemJob;
use App\Models\NewsItem;
use App\Processing\NewsAiProcessor;
use App\Processing\NewsSummaryResult;
use App\Processing\NewsTranslationResult;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
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

        Queue::assertPushed(FetchNewsItemArticleContentJob::class, static fn (FetchNewsItemArticleContentJob $job): bool => $job->newsItemId === $item->id);
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
            'article_content_status' => 'pending',
            'translation_status' => 'pending',
            'summary_status' => 'pending',
        ]);
    }

    public function testPendingArticleContentEnqueuesContentFetchJob(): void
    {
        Queue::fake();
        $item = $this->createNewsItem();

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertPushed(FetchNewsItemArticleContentJob::class, 1);
        Queue::assertPushed(FetchNewsItemArticleContentJob::class, static fn (FetchNewsItemArticleContentJob $job): bool => $job->newsItemId === $item->id);
        $this->assertDatabaseHas('news_items', [
            'id' => $item->id,
            'article_content_status' => 'queued',
        ]);
    }

    public function testQueuedArticleContentDoesNotEnqueueDuplicateContentFetchJob(): void
    {
        Queue::fake();
        $item = $this->createNewsItem([
            'article_content_status' => 'queued',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertDatabaseHas('news_items', [
            'id' => $item->id,
            'article_content_status' => 'queued',
        ]);
    }

    public function testProcessingArticleContentDoesNotEnqueueTranslation(): void
    {
        Queue::fake();
        $this->createNewsItem([
            'article_content_status' => 'processing',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function testCompletedArticleContentWithPendingTranslationEnqueuesTranslationJob(): void
    {
        Queue::fake();
        $item = $this->createNewsItem([
            'article_content_status' => 'completed',
            'translation_status' => 'pending',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertPushed(TranslateNewsItemJob::class, 1);
        Queue::assertPushed(TranslateNewsItemJob::class, static fn (TranslateNewsItemJob $job): bool => $job->newsItemId === $item->id);
        $this->assertDatabaseHas('news_items', [
            'id' => $item->id,
            'translation_status' => 'queued',
        ]);
    }

    public function testSkippedArticleContentFallsBackToTranslation(): void
    {
        Queue::fake();
        $item = $this->createNewsItem([
            'article_content_status' => 'skipped',
            'translation_status' => 'pending',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertPushed(TranslateNewsItemJob::class, static fn (TranslateNewsItemJob $job): bool => $job->newsItemId === $item->id);
        $this->assertDatabaseHas('news_items', [
            'id' => $item->id,
            'translation_status' => 'queued',
        ]);
    }

    public function testQueuedTranslationDoesNotEnqueueDuplicateTranslationJob(): void
    {
        Queue::fake();
        $this->createNewsItem([
            'article_content_status' => 'completed',
            'translation_status' => 'queued',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function testProcessingTranslationDoesNotEnqueueSummary(): void
    {
        Queue::fake();
        $this->createNewsItem([
            'article_content_status' => 'completed',
            'translation_status' => 'processing',
            'summary_status' => 'pending',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function testCompletedTranslationWithPendingSummaryEnqueuesSummaryJob(): void
    {
        Queue::fake();
        $item = $this->createNewsItem([
            'article_content_status' => 'completed',
            'translation_status' => 'completed',
            'summary_status' => 'pending',
            'translated_title' => '[ja] Completed title',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertNotPushed(TranslateNewsItemJob::class);
        Queue::assertPushed(SummarizeNewsItemJob::class, 1);
        Queue::assertPushed(SummarizeNewsItemJob::class, static fn (SummarizeNewsItemJob $job): bool => $job->newsItemId === $item->id);
        $this->assertDatabaseHas('news_items', [
            'id' => $item->id,
            'summary_status' => 'queued',
        ]);
    }

    public function testCompletedSummaryEnqueuesNothing(): void
    {
        Queue::fake();
        $this->createNewsItem([
            'article_content_status' => 'completed',
            'translation_status' => 'completed',
            'summary_status' => 'completed',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function testLimitIsRespectedAsDispatchedJobCount(): void
    {
        Queue::fake();
        $this->createNewsItem();
        $this->createNewsItem();

        $this->enqueueProcessing(['--limit' => 1])
            ->assertSuccessful();

        Queue::assertPushed(FetchNewsItemArticleContentJob::class, 1);
        $this->assertDatabaseCount('news_items', 2);
        $this->assertDatabaseCountByArticleContentStatus('queued', 1);
    }

    public function testSourceFilterWorks(): void
    {
        Queue::fake();
        $this->createNewsItem(['source_key' => 'hacker_news']);
        $reutersItem = $this->createNewsItem(['source_key' => 'reuters_top']);

        $this->enqueueProcessing(['--source' => 'reuters_top'])
            ->assertSuccessful();

        Queue::assertPushed(FetchNewsItemArticleContentJob::class, 1);
        Queue::assertPushed(FetchNewsItemArticleContentJob::class, static fn (FetchNewsItemArticleContentJob $job): bool => $job->newsItemId === $reutersItem->id);
        $this->assertDatabaseCountByArticleContentStatus('queued', 1);
    }

    public function testDryRunOutputsDecisionInformation(): void
    {
        Queue::fake();
        $item = $this->createNewsItem();
        $decisions = [];

        Log::listen(static function (MessageLogged $message) use (&$decisions): void {
            if ($message->message === 'News item processing decision.') {
                $decisions[] = $message->context;
            }
        });

        $this->enqueueProcessing(['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN: news_item=' . $item->id)
            ->assertSuccessful();

        Queue::assertNothingPushed();
        self::assertSame('content', $decisions[0]['stage'] ?? null);
        self::assertSame('FetchNewsItemArticleContentJob', $decisions[0]['job'] ?? null);
        self::assertSame('article_content_pending', $decisions[0]['reason'] ?? null);
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
            'discussion_url' => null,
            'title' => 'Example title ' . $sequence,
            'excerpt' => 'Example excerpt ' . $sequence,
            'published_at' => CarbonImmutable::parse('2026-05-23 12:00:00'),
            'fetched_at' => CarbonImmutable::parse('2026-05-23 12:05:00'),
            'content_hash' => hash('sha256', 'content-' . $sequence),
            'processing_status' => 'fetched',
            'translation_status' => 'pending',
            'summary_status' => 'pending',
            'article_content_status' => 'pending',
            'article_content_error' => null,
            'error_message' => null,
            'processing_error' => null,
        ], $attributes));
    }

    private function assertDatabaseCountByArticleContentStatus(string $status, int $expectedCount): void
    {
        $items = NewsItem::query()->where('article_content_status', $status)->get();

        self::assertCount($expectedCount, $items);
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
