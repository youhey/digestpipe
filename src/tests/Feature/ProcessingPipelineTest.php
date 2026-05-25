<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeNewsItemJob;
use App\Jobs\FetchNewsItemArticleContentJob;
use App\Models\NewsItem;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

/**
 * @internal
 */
class ProcessingPipelineTest extends TestCase
{
    use RefreshDatabase;

    private int $newsItemSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config(['digestpipe.selection.enabled' => false]);
    }

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
            'analysis_status' => 'pending',
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
        $this->createNewsItem([
            'article_content_status' => 'queued',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function testProcessingArticleContentDoesNotEnqueueAnalysis(): void
    {
        Queue::fake();
        $this->createNewsItem([
            'article_content_status' => 'processing',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function testCompletedArticleContentWithPendingAnalysisEnqueuesAnalysisJob(): void
    {
        Queue::fake();
        $item = $this->createNewsItem([
            'article_content_status' => 'completed',
            'analysis_status' => 'pending',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertPushed(AnalyzeNewsItemJob::class, 1);
        Queue::assertPushed(AnalyzeNewsItemJob::class, static fn (AnalyzeNewsItemJob $job): bool => $job->newsItemId === $item->id);
        $this->assertDatabaseHas('news_items', [
            'id' => $item->id,
            'analysis_status' => 'queued',
        ]);
    }

    public function testSkippedArticleContentFallsBackToAnalysis(): void
    {
        Queue::fake();
        $item = $this->createNewsItem([
            'article_content_status' => 'skipped',
            'analysis_status' => 'pending',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertPushed(AnalyzeNewsItemJob::class, static fn (AnalyzeNewsItemJob $job): bool => $job->newsItemId === $item->id);
        $this->assertDatabaseHas('news_items', [
            'id' => $item->id,
            'analysis_status' => 'queued',
        ]);
    }

    public function testQueuedAnalysisDoesNotEnqueueDuplicateAnalysisJob(): void
    {
        Queue::fake();
        $this->createNewsItem([
            'article_content_status' => 'completed',
            'analysis_status' => 'queued',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function testProcessingAnalysisDoesNotEnqueueDuplicateAnalysisJob(): void
    {
        Queue::fake();
        $this->createNewsItem([
            'article_content_status' => 'completed',
            'analysis_status' => 'processing',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function testCompletedAnalysisEnqueuesNothingByDefault(): void
    {
        Queue::fake();
        $this->createNewsItem([
            'article_content_status' => 'completed',
            'analysis_status' => 'completed',
            'analysis_json' => $this->analysisJson('Completed analysis'),
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function testSelectionSelectedItemCanEnqueueContentFetch(): void
    {
        $this->enableSelectionForTests();
        Queue::fake();
        $item = $this->createNewsItem([
            'title' => 'Laravel queues in production',
            'excerpt' => 'Practical framework article',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertPushed(FetchNewsItemArticleContentJob::class, static fn (FetchNewsItemArticleContentJob $job): bool => $job->newsItemId === $item->id);
        $this->assertDatabaseHas('news_items', [
            'id' => $item->id,
            'selection_status' => 'selected',
            'selection_score' => 15,
            'selection_reason' => 'above_analysis_threshold',
            'article_content_status' => 'queued',
        ]);
    }

    public function testSelectionSkippedItemDoesNotEnqueueContentFetchOrAnalysis(): void
    {
        $this->enableSelectionForTests();
        Queue::fake();
        $item = $this->createNewsItem([
            'title' => 'Crypto blockchain token news',
            'excerpt' => 'Investment update',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertDatabaseHas('news_items', [
            'id' => $item->id,
            'selection_status' => 'skipped',
            'selection_score' => -210,
            'selection_reason' => 'below_skip_threshold',
            'article_content_status' => 'pending',
            'analysis_status' => 'pending',
        ]);
    }

    public function testAlreadySkippedSelectionDoesNotEnqueueContentFetchOrAnalysis(): void
    {
        $this->enableSelectionForTests();
        Queue::fake();
        $this->createNewsItem([
            'selection_status' => 'skipped',
            'selection_score' => -100,
            'selection_reason' => 'below_skip_threshold',
            'article_content_status' => 'completed',
            'analysis_status' => 'pending',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function testSelectionIsIdempotentForAlreadySelectedItems(): void
    {
        $this->enableSelectionForTests();
        Queue::fake();
        $evaluatedAt = CarbonImmutable::parse('2026-05-24T00:00:00Z');
        $item = $this->createNewsItem([
            'title' => 'Crypto title should not be re-evaluated',
            'selection_status' => 'selected',
            'selection_score' => 15,
            'selection_reason' => 'above_analysis_threshold',
            'selection_evaluated_at' => $evaluatedAt,
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertPushed(FetchNewsItemArticleContentJob::class, static fn (FetchNewsItemArticleContentJob $job): bool => $job->newsItemId === $item->id);
        $item->refresh();
        self::assertSame('selected', $item->selection_status);
        self::assertSame(15, $item->selection_score);
        self::assertSame($evaluatedAt->toJSON(), $item->selection_evaluated_at?->toJSON());
    }

    public function testSelectionSelectedCompletedContentCanEnqueueAnalysis(): void
    {
        $this->enableSelectionForTests();
        Queue::fake();
        $item = $this->createNewsItem([
            'title' => 'AWS Laravel deployment',
            'selection_status' => 'pending',
            'article_content_status' => 'completed',
            'analysis_status' => 'pending',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertPushed(AnalyzeNewsItemJob::class, static fn (AnalyzeNewsItemJob $job): bool => $job->newsItemId === $item->id);
        $this->assertDatabaseHas('news_items', [
            'id' => $item->id,
            'selection_status' => 'selected',
            'analysis_status' => 'queued',
        ]);
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

    public function testStageFilterCanLimitToAnalysis(): void
    {
        Queue::fake();
        $this->createNewsItem([
            'article_content_status' => 'pending',
        ]);
        $analysisItem = $this->createNewsItem([
            'article_content_status' => 'completed',
            'analysis_status' => 'pending',
        ]);

        $this->enqueueProcessing(['--stage' => 'analysis'])
            ->assertSuccessful();

        Queue::assertNotPushed(FetchNewsItemArticleContentJob::class);
        Queue::assertPushed(AnalyzeNewsItemJob::class, 1);
        Queue::assertPushed(AnalyzeNewsItemJob::class, static fn (AnalyzeNewsItemJob $job): bool => $job->newsItemId === $analysisItem->id);
    }

    public function testInvalidStageReturnsError(): void
    {
        $this->enqueueProcessing(['--stage' => 'unknown'])
            ->assertExitCode(2);
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

    public function testDryRunOutputsAnalysisDecisionWithoutMutation(): void
    {
        Queue::fake();
        $item = $this->createNewsItem([
            'article_content_status' => 'completed',
            'analysis_status' => 'pending',
        ]);

        $this->enqueueProcessing(['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN: news_item=' . $item->id . ' source=example stage=analysis job=AnalyzeNewsItemJob reason=article_content_completed_analysis_pending')
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertDatabaseHas('news_items', [
            'id' => $item->id,
            'analysis_status' => 'pending',
        ]);
    }

    public function testDryRunOutputsNoDefaultFollowUpForCompletedAnalysis(): void
    {
        Queue::fake();
        $item = $this->createNewsItem([
            'article_content_status' => 'completed',
            'analysis_status' => 'completed',
            'analysis_json' => $this->analysisJson('Completed analysis'),
        ]);

        $this->enqueueProcessing(['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN: news_item=' . $item->id . ' source=example stage=none job=none reason=analysis_completed')
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function testCompletedAnalysisHelpersRequireCompletedAnalysisJson(): void
    {
        $readyItem = $this->createNewsItem([
            'article_content_status' => 'completed',
            'analysis_status' => 'completed',
            'analysis_json' => $this->analysisJson('Ready analysis'),
        ]);
        $missingJsonItem = $this->createNewsItem([
            'article_content_status' => 'completed',
            'analysis_status' => 'completed',
            'analysis_json' => null,
        ]);
        $pendingItem = $this->createNewsItem([
            'article_content_status' => 'completed',
            'analysis_status' => 'pending',
            'analysis_json' => $this->analysisJson('Not ready analysis'),
        ]);

        self::assertTrue($readyItem->hasCompletedAnalysis());
        self::assertTrue($readyItem->readyForDigest());
        self::assertFalse($missingJsonItem->hasCompletedAnalysis());
        self::assertFalse($pendingItem->readyForDigest());
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
                'original' => 'Example title',
                'normalized' => 'Example title',
            ],
            'content' => [
                'brief' => $brief,
                'detailed_summary' => $brief,
                'key_points' => [$brief],
                'background' => null,
                'why_it_matters' => null,
                'limitations' => null,
            ],
            'classification' => [
                'content_type' => 'news_article',
                'topics' => ['general'],
                'entities' => [],
                'importance' => 3,
                'confidence' => 0.8,
            ],
        ];
    }

    private function assertDatabaseCountByArticleContentStatus(string $status, int $expectedCount): void
    {
        $items = NewsItem::query()->where('article_content_status', $status)->get();

        self::assertCount($expectedCount, $items);
    }

    private function enableSelectionForTests(): void
    {
        config([
            'digestpipe.selection' => [
                'enabled' => true,
                'default_score' => 0,
                'analysis_threshold' => 10,
                'skip_threshold' => -50,
                'positive_keywords' => [
                    'Laravel' => 15,
                    'AWS' => 12,
                    'PHP' => 5,
                ],
                'negative_keywords' => [
                    'crypto' => -100,
                    'blockchain' => -100,
                    'token' => -10,
                ],
            ],
        ]);
    }
}
