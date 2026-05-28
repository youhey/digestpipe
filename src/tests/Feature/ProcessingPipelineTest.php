<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeDigestItemJob;
use App\Jobs\FetchDigestItemArticleContentJob;
use App\Models\DigestItem;
use App\Models\FeedSource;
use App\Models\SelectionEvaluation;
use App\Models\SelectionKeyword;
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

    private int $digestItemSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config(['digestpipe.selection.enabled' => false]);
        $this->configureFeedSourcesForTests([
            ['key' => 'hacker_news', 'enabled' => true, 'analysis_enabled' => true],
        ]);
    }

    public function testEnqueueCommandCanRun(): void
    {
        Queue::fake();
        $item = $this->createDigestItem();

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertPushed(FetchDigestItemArticleContentJob::class, static fn (FetchDigestItemArticleContentJob $job): bool => $job->digestItemId === $item->id);
    }

    public function testDryRunDoesNotDispatchJobsOrChangeStatuses(): void
    {
        Queue::fake();
        $item = $this->createDigestItem();

        $this->enqueueProcessing(['--dry-run' => true])
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertDatabaseHas('digest_items', [
            'id' => $item->id,
            'article_content_status' => 'pending',
            'analysis_status' => 'pending',
        ]);
    }

    public function testPendingArticleContentEnqueuesContentFetchJob(): void
    {
        Queue::fake();
        $item = $this->createDigestItem();

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertPushed(FetchDigestItemArticleContentJob::class, 1);
        Queue::assertPushed(FetchDigestItemArticleContentJob::class, static fn (FetchDigestItemArticleContentJob $job): bool => $job->digestItemId === $item->id);
        $this->assertDatabaseHas('digest_items', [
            'id' => $item->id,
            'article_content_status' => 'queued',
        ]);
    }

    public function testQueuedArticleContentDoesNotEnqueueDuplicateContentFetchJob(): void
    {
        Queue::fake();
        $this->createDigestItem([
            'article_content_status' => 'queued',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function testProcessingArticleContentDoesNotEnqueueAnalysis(): void
    {
        Queue::fake();
        $this->createDigestItem([
            'article_content_status' => 'processing',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function testCompletedArticleContentWithPendingAnalysisEnqueuesAnalysisJob(): void
    {
        Queue::fake();
        $item = $this->createDigestItem([
            'article_content_status' => 'completed',
            'analysis_status' => 'pending',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertPushed(AnalyzeDigestItemJob::class, 1);
        Queue::assertPushed(AnalyzeDigestItemJob::class, static fn (AnalyzeDigestItemJob $job): bool => $job->digestItemId === $item->id);
        $this->assertDatabaseHas('digest_items', [
            'id' => $item->id,
            'analysis_status' => 'queued',
        ]);
    }

    public function testSkippedArticleContentFallsBackToAnalysis(): void
    {
        Queue::fake();
        $item = $this->createDigestItem([
            'article_content_status' => 'skipped',
            'analysis_status' => 'pending',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertPushed(AnalyzeDigestItemJob::class, static fn (AnalyzeDigestItemJob $job): bool => $job->digestItemId === $item->id);
        $this->assertDatabaseHas('digest_items', [
            'id' => $item->id,
            'analysis_status' => 'queued',
        ]);
    }

    public function testQueuedAnalysisDoesNotEnqueueDuplicateAnalysisJob(): void
    {
        Queue::fake();
        $this->createDigestItem([
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
        $this->createDigestItem([
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
        $this->createDigestItem([
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
        $item = $this->createDigestItem([
            'title' => 'Laravel queues in production',
            'excerpt' => 'Practical framework article',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertPushed(FetchDigestItemArticleContentJob::class, static fn (FetchDigestItemArticleContentJob $job): bool => $job->digestItemId === $item->id);
        $this->assertDatabaseHas('digest_items', [
            'id' => $item->id,
            'selection_status' => 'needs_content',
            'selection_score' => 15,
            'selection_reason' => 'pre_content_selection_deferred',
            'article_content_status' => 'queued',
        ]);
    }

    public function testPreContentSelectionWritesEvaluationHistory(): void
    {
        $this->enableSelectionForTests();
        Queue::fake();
        $item = $this->createDigestItem([
            'title' => 'Laravel queues in production',
            'excerpt' => 'Token noise should also be visible.',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        $evaluation = SelectionEvaluation::query()->firstOrFail();

        self::assertSame($item->id, $evaluation->digest_item_id);
        self::assertSame('hacker_news', $evaluation->source_key);
        self::assertSame('pre_content', $evaluation->phase);
        self::assertSame('needs_content', $evaluation->status);
        self::assertSame(5, $evaluation->score);
        self::assertSame('pre_content_selection_deferred', $evaluation->reason);
        self::assertSame(['Laravel'], $evaluation->matched_positive_keywords);
        self::assertSame(['token'], $evaluation->matched_negative_keywords);
        self::assertSame(false, $evaluation->input_summary['article_content_present']);
        self::assertSame(0, $evaluation->input_summary['article_content_length']);
        self::assertSame(10, $evaluation->selection_config_summary['analysis_threshold'] ?? null);
        $this->assertDatabaseHas('digest_items', [
            'id' => $item->id,
            'selection_status' => 'needs_content',
            'selection_score' => 5,
            'selection_reason' => 'pre_content_selection_deferred',
        ]);
    }

    public function testPreContentLowScoreItemCanEnqueueContentFetch(): void
    {
        $this->enableSelectionForTests();
        Queue::fake();
        $item = $this->createDigestItem([
            'title' => 'Plain article without keywords',
            'excerpt' => 'Short feed excerpt',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertPushed(FetchDigestItemArticleContentJob::class, static fn (FetchDigestItemArticleContentJob $job): bool => $job->digestItemId === $item->id);
        $this->assertDatabaseHas('digest_items', [
            'id' => $item->id,
            'selection_status' => 'needs_content',
            'selection_score' => 0,
            'selection_reason' => 'pre_content_selection_deferred',
            'article_content_status' => 'queued',
        ]);
    }

    public function testSelectionSkippedItemDoesNotEnqueueContentFetchOrAnalysis(): void
    {
        $this->enableSelectionForTests();
        Queue::fake();
        $item = $this->createDigestItem([
            'title' => 'Crypto blockchain token news',
            'excerpt' => 'Investment update',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertDatabaseHas('digest_items', [
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
        $this->createDigestItem([
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
        $item = $this->createDigestItem([
            'title' => 'Crypto title should not be re-evaluated',
            'selection_status' => 'selected',
            'selection_score' => 15,
            'selection_reason' => 'above_analysis_threshold',
            'selection_evaluated_at' => $evaluatedAt,
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertPushed(FetchDigestItemArticleContentJob::class, static fn (FetchDigestItemArticleContentJob $job): bool => $job->digestItemId === $item->id);
        $item->refresh();
        self::assertSame('selected', $item->selection_status);
        self::assertSame(15, $item->selection_score);
        self::assertSame($evaluatedAt->toJSON(), $item->selection_evaluated_at?->toJSON());
    }

    public function testSelectionSelectedCompletedContentCanEnqueueAnalysis(): void
    {
        $this->enableSelectionForTests();
        Queue::fake();
        $item = $this->createDigestItem([
            'title' => 'AWS Laravel deployment',
            'selection_status' => 'pending',
            'article_content_status' => 'completed',
            'analysis_status' => 'pending',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertPushed(AnalyzeDigestItemJob::class, static fn (AnalyzeDigestItemJob $job): bool => $job->digestItemId === $item->id);
        $this->assertDatabaseHas('digest_items', [
            'id' => $item->id,
            'selection_status' => 'selected',
            'analysis_status' => 'queued',
        ]);
    }

    public function testPostContentLowScoreItemIsSkippedBeforeAnalysis(): void
    {
        $this->enableSelectionForTests();
        Queue::fake();
        $item = $this->createDigestItem([
            'title' => 'Plain article without keywords',
            'excerpt' => 'Short feed excerpt',
            'selection_status' => 'needs_content',
            'selection_score' => 0,
            'selection_reason' => 'pre_content_selection_deferred',
            'article_content_status' => 'completed',
            'article_content_text' => 'Article body with no configured keyword.',
            'analysis_status' => 'pending',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertDatabaseHas('digest_items', [
            'id' => $item->id,
            'selection_status' => 'skipped',
            'selection_score' => 0,
            'selection_reason' => 'below_analysis_threshold',
            'analysis_status' => 'pending',
        ]);
    }

    public function testPostContentThresholdScoreItemIsSelectedAndEnqueuesAnalysis(): void
    {
        $this->enableSelectionForTests();
        Queue::fake();
        $item = $this->createDigestItem([
            'title' => 'Plain article title',
            'excerpt' => 'Short feed excerpt',
            'selection_status' => 'needs_content',
            'selection_score' => 0,
            'selection_reason' => 'pre_content_selection_deferred',
            'article_content_status' => 'completed',
            'article_content_text' => 'The article explains AWS deployment.',
            'analysis_status' => 'pending',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertPushed(AnalyzeDigestItemJob::class, static fn (AnalyzeDigestItemJob $job): bool => $job->digestItemId === $item->id);
        $this->assertDatabaseHas('digest_items', [
            'id' => $item->id,
            'selection_status' => 'selected',
            'selection_score' => 12,
            'selection_reason' => 'above_analysis_threshold',
            'analysis_status' => 'queued',
        ]);
    }

    public function testPostContentSelectionWritesEvaluationHistoryWithoutRawArticleContent(): void
    {
        $this->enableSelectionForTests();
        Queue::fake();
        $articleContent = 'The article explains AWS deployment with Laravel in detail.';
        $item = $this->createDigestItem([
            'title' => 'Plain article title',
            'excerpt' => 'Short feed excerpt',
            'selection_status' => 'needs_content',
            'selection_score' => 0,
            'selection_reason' => 'pre_content_selection_deferred',
            'article_content_status' => 'completed',
            'article_content_text' => $articleContent,
            'analysis_status' => 'pending',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        $evaluation = SelectionEvaluation::query()->firstOrFail();

        self::assertSame($item->id, $evaluation->digest_item_id);
        self::assertSame('post_content', $evaluation->phase);
        self::assertSame('selected', $evaluation->status);
        self::assertSame(27, $evaluation->score);
        self::assertSame('above_analysis_threshold', $evaluation->reason);
        self::assertSame(['Laravel', 'AWS'], $evaluation->matched_positive_keywords);
        self::assertSame([], $evaluation->matched_negative_keywords);
        self::assertSame(true, $evaluation->input_summary['article_content_present']);
        self::assertSame(mb_strlen($articleContent), $evaluation->input_summary['article_content_length']);
        self::assertArrayNotHasKey('article_content_text', $evaluation->input_summary);
        self::assertArrayNotHasKey('article_content', $evaluation->input_summary);
        $this->assertDatabaseHas('digest_items', [
            'id' => $item->id,
            'selection_status' => 'selected',
            'selection_score' => 27,
            'selection_reason' => 'above_analysis_threshold',
        ]);
    }

    public function testMultipleSelectionEvaluationsAppendHistoryRows(): void
    {
        $this->enableSelectionForTests();
        Queue::fake();
        $item = $this->createDigestItem([
            'title' => 'Plain article title',
            'excerpt' => 'Short feed excerpt',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        $item->refresh();
        $item->forceFill([
            'article_content_status' => 'completed',
            'article_content_text' => 'The article explains AWS deployment.',
            'analysis_status' => 'pending',
        ])->save();

        $this->enqueueProcessing()
            ->assertSuccessful();

        $evaluations = SelectionEvaluation::query()
            ->where('digest_item_id', $item->id)
            ->get()
            ->sortBy('id')
            ->values();

        self::assertCount(2, $evaluations);
        $preContentEvaluation = $evaluations->get(0);
        $postContentEvaluation = $evaluations->get(1);

        self::assertInstanceOf(SelectionEvaluation::class, $preContentEvaluation);
        self::assertInstanceOf(SelectionEvaluation::class, $postContentEvaluation);
        self::assertSame('pre_content', $preContentEvaluation->phase);
        self::assertSame('needs_content', $preContentEvaluation->status);
        self::assertSame('post_content', $postContentEvaluation->phase);
        self::assertSame('selected', $postContentEvaluation->status);
    }

    public function testHackerNewsLikeCommentsExcerptCanProceedToContentFetch(): void
    {
        $this->enableSelectionForTests();
        Queue::fake();
        $item = $this->createDigestItem([
            'source_key' => 'hacker_news',
            'source_name' => 'Hacker News',
            'title' => 'A database internals article',
            'excerpt' => '<a href="https://news.ycombinator.com/item?id=123">Comments</a>',
        ]);

        $this->enqueueProcessing()
            ->assertSuccessful();

        Queue::assertPushed(FetchDigestItemArticleContentJob::class, static fn (FetchDigestItemArticleContentJob $job): bool => $job->digestItemId === $item->id);
        $this->assertDatabaseHas('digest_items', [
            'id' => $item->id,
            'selection_status' => 'needs_content',
            'selection_score' => 0,
            'selection_reason' => 'pre_content_selection_deferred',
            'article_content_status' => 'queued',
        ]);
    }

    public function testLimitIsRespectedAsDispatchedJobCount(): void
    {
        Queue::fake();
        $this->createDigestItem();
        $this->createDigestItem();

        $this->enqueueProcessing(['--limit' => 1])
            ->assertSuccessful();

        Queue::assertPushed(FetchDigestItemArticleContentJob::class, 1);
        $this->assertDatabaseCount('digest_items', 2);
        $this->assertDatabaseCountByArticleContentStatus('queued', 1);
    }

    public function testPerSourceLimitLimitsCandidatesPerSource(): void
    {
        Queue::fake();
        $this->configureFeedSourcesForTests([
            ['key' => 'hacker_news', 'enabled' => true, 'analysis_enabled' => true],
            ['key' => 'php_weekly', 'enabled' => true, 'analysis_enabled' => true],
        ]);
        $this->createDigestItem(['source_key' => 'hacker_news']);
        $this->createDigestItem(['source_key' => 'hacker_news']);
        $this->createDigestItem(['source_key' => 'hacker_news']);
        $this->createDigestItem(['source_key' => 'php_weekly']);
        $this->createDigestItem(['source_key' => 'php_weekly']);
        $this->createDigestItem(['source_key' => 'php_weekly']);

        $this->enqueueProcessing(['--limit' => 100, '--per-source-limit' => 2])
            ->expectsOutputToContain('Source summary:')
            ->expectsOutputToContain('source=hacker_news candidates=2 planned=2 skipped=0 content=2 analysis=0')
            ->expectsOutputToContain('source=php_weekly candidates=2 planned=2 skipped=0 content=2 analysis=0')
            ->assertSuccessful();

        Queue::assertPushed(FetchDigestItemArticleContentJob::class, 4);
        $this->assertQueuedCountForSource('hacker_news', 2);
        $this->assertQueuedCountForSource('php_weekly', 2);
    }

    public function testGlobalLimitIsRespectedAfterPerSourceCandidateCollection(): void
    {
        Queue::fake();
        $this->configureFeedSourcesForTests([
            ['key' => 'hacker_news', 'enabled' => true, 'analysis_enabled' => true],
            ['key' => 'php_weekly', 'enabled' => true, 'analysis_enabled' => true],
        ]);
        $this->createDigestItem(['source_key' => 'hacker_news']);
        $this->createDigestItem(['source_key' => 'hacker_news']);
        $this->createDigestItem(['source_key' => 'php_weekly']);
        $this->createDigestItem(['source_key' => 'php_weekly']);

        $this->enqueueProcessing(['--limit' => 3, '--per-source-limit' => 2])
            ->assertSuccessful();

        Queue::assertPushed(FetchDigestItemArticleContentJob::class, 3);
        $this->assertDatabaseCountByArticleContentStatus('queued', 3);
    }

    public function testMultipleSourcesAreProcessedFairlyWhenSourceIsNotSpecified(): void
    {
        Queue::fake();
        $this->configureFeedSourcesForTests([
            ['key' => 'hacker_news', 'enabled' => true, 'analysis_enabled' => true],
            ['key' => 'php_weekly', 'enabled' => true, 'analysis_enabled' => true],
        ]);
        $this->createDigestItem(['source_key' => 'hacker_news']);
        $this->createDigestItem(['source_key' => 'hacker_news']);
        $this->createDigestItem(['source_key' => 'hacker_news']);
        $this->createDigestItem(['source_key' => 'php_weekly']);

        $this->enqueueProcessing(['--limit' => 100, '--per-source-limit' => 1])
            ->assertSuccessful();

        Queue::assertPushed(FetchDigestItemArticleContentJob::class, 2);
        $this->assertQueuedCountForSource('hacker_news', 1);
        $this->assertQueuedCountForSource('php_weekly', 1);
    }

    public function testSourceFilterWorks(): void
    {
        Queue::fake();
        $this->createDigestItem(['source_key' => 'hacker_news']);
        $reutersItem = $this->createDigestItem(['source_key' => 'reuters_top']);

        $this->enqueueProcessing(['--source' => 'reuters_top'])
            ->assertSuccessful();

        Queue::assertPushed(FetchDigestItemArticleContentJob::class, 1);
        Queue::assertPushed(FetchDigestItemArticleContentJob::class, static fn (FetchDigestItemArticleContentJob $job): bool => $job->digestItemId === $reutersItem->id);
        $this->assertDatabaseCountByArticleContentStatus('queued', 1);
    }

    public function testSourceFilterUsesLimitAndIgnoresPerSourceLimit(): void
    {
        Queue::fake();
        $this->createDigestItem(['source_key' => 'reuters_top']);
        $this->createDigestItem(['source_key' => 'reuters_top']);
        $this->createDigestItem(['source_key' => 'reuters_top']);
        $this->createDigestItem(['source_key' => 'hacker_news']);

        $this->enqueueProcessing([
            '--source' => 'reuters_top',
            '--limit' => 2,
            '--per-source-limit' => 1,
        ])->assertSuccessful();

        Queue::assertPushed(FetchDigestItemArticleContentJob::class, 2);
        $this->assertQueuedCountForSource('reuters_top', 2);
        $this->assertQueuedCountForSource('hacker_news', 0);
    }

    public function testDisabledSourcesAreIgnoredInFairSourceMode(): void
    {
        Queue::fake();
        $this->configureFeedSourcesForTests([
            ['key' => 'hacker_news', 'enabled' => true, 'analysis_enabled' => true],
            ['key' => 'disabled_source', 'enabled' => false, 'analysis_enabled' => false],
        ]);
        $this->createDigestItem(['source_key' => 'hacker_news']);
        $this->createDigestItem(['source_key' => 'disabled_source']);

        $this->enqueueProcessing(['--limit' => 100, '--per-source-limit' => 10])
            ->assertSuccessful();

        Queue::assertPushed(FetchDigestItemArticleContentJob::class, 1);
        $this->assertQueuedCountForSource('hacker_news', 1);
        $this->assertQueuedCountForSource('disabled_source', 0);
    }

    public function testAnalysisDisabledSourcesAreIgnoredInFairSourceMode(): void
    {
        Queue::fake();
        $this->configureFeedSourcesForTests([
            ['key' => 'hacker_news', 'enabled' => true, 'analysis_enabled' => true],
            ['key' => 'candidate_source', 'enabled' => true, 'analysis_enabled' => false],
        ]);
        $this->createDigestItem(['source_key' => 'hacker_news']);
        $this->createDigestItem(['source_key' => 'candidate_source']);

        $this->enqueueProcessing(['--limit' => 100, '--per-source-limit' => 10])
            ->assertSuccessful();

        Queue::assertPushed(FetchDigestItemArticleContentJob::class, 1);
        $this->assertQueuedCountForSource('hacker_news', 1);
        $this->assertQueuedCountForSource('candidate_source', 0);
    }

    public function testStageFilterCanLimitToAnalysis(): void
    {
        Queue::fake();
        $this->createDigestItem([
            'article_content_status' => 'pending',
        ]);
        $analysisItem = $this->createDigestItem([
            'article_content_status' => 'completed',
            'analysis_status' => 'pending',
        ]);

        $this->enqueueProcessing(['--stage' => 'analysis'])
            ->assertSuccessful();

        Queue::assertNotPushed(FetchDigestItemArticleContentJob::class);
        Queue::assertPushed(AnalyzeDigestItemJob::class, 1);
        Queue::assertPushed(AnalyzeDigestItemJob::class, static fn (AnalyzeDigestItemJob $job): bool => $job->digestItemId === $analysisItem->id);
    }

    public function testInvalidStageReturnsError(): void
    {
        $this->enqueueProcessing(['--stage' => 'unknown'])
            ->assertExitCode(2);
    }

    public function testDryRunOutputsDecisionInformation(): void
    {
        Queue::fake();
        $item = $this->createDigestItem();
        $decisions = [];

        Log::listen(static function (MessageLogged $message) use (&$decisions): void {
            if ($message->message === 'Digest item processing decision.') {
                $decisions[] = $message->context;
            }
        });

        $this->enqueueProcessing(['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN: digest_item=' . $item->id)
            ->assertSuccessful();

        Queue::assertNothingPushed();
        self::assertSame('content', $decisions[0]['stage'] ?? null);
        self::assertSame('FetchDigestItemArticleContentJob', $decisions[0]['job'] ?? null);
        self::assertSame('article_content_pending', $decisions[0]['reason'] ?? null);
    }

    public function testDryRunOutputsAnalysisDecisionWithoutMutation(): void
    {
        Queue::fake();
        $item = $this->createDigestItem([
            'article_content_status' => 'completed',
            'analysis_status' => 'pending',
        ]);

        $this->enqueueProcessing(['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN: digest_item=' . $item->id . ' source=hacker_news stage=analysis job=AnalyzeDigestItemJob reason=article_content_completed_analysis_pending')
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertDatabaseHas('digest_items', [
            'id' => $item->id,
            'analysis_status' => 'pending',
        ]);
    }

    public function testDryRunOutputsNoDefaultFollowUpForCompletedAnalysis(): void
    {
        Queue::fake();
        $this->createDigestItem([
            'article_content_status' => 'completed',
            'analysis_status' => 'completed',
            'analysis_json' => $this->analysisJson('Completed analysis'),
        ]);

        $this->enqueueProcessing(['--dry-run' => true])
            ->expectsOutputToContain('Processing enqueue finished. Candidates: 0, planned: 0, skipped: 0, content: 0, analysis: 0.')
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function testCompletedAnalysisHelpersRequireCompletedAnalysisJson(): void
    {
        $readyItem = $this->createDigestItem([
            'article_content_status' => 'completed',
            'analysis_status' => 'completed',
            'analysis_json' => $this->analysisJson('Ready analysis'),
        ]);
        $missingJsonItem = $this->createDigestItem([
            'article_content_status' => 'completed',
            'analysis_status' => 'completed',
            'analysis_json' => null,
        ]);
        $pendingItem = $this->createDigestItem([
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
    private function createDigestItem(array $attributes = []): DigestItem
    {
        ++$this->digestItemSequence;
        $sequence = $this->digestItemSequence;

        return DigestItem::query()->create(array_merge([
            'source_key' => 'hacker_news',
            'source_name' => 'Hacker News',
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
        $items = DigestItem::query()->where('article_content_status', $status)->get();

        self::assertCount($expectedCount, $items);
    }

    private function assertQueuedCountForSource(string $sourceKey, int $expectedCount): void
    {
        $items = DigestItem::query()
            ->where('source_key', $sourceKey)
            ->where('article_content_status', 'queued')
            ->get();

        self::assertCount($expectedCount, $items);
    }

    /**
     * @param list<array{key: string, enabled: bool, analysis_enabled: bool}> $sources
     */
    private function configureFeedSourcesForTests(array $sources): void
    {
        FeedSource::query()->delete();

        foreach ($sources as $index => $source) {
            FeedSource::query()->create([
                'key' => $source['key'],
                'name' => $source['key'],
                'url' => 'https://example.test/' . $source['key'] . '.xml',
                'language' => 'en',
                'enabled' => $source['enabled'],
                'analysis_enabled' => $source['analysis_enabled'],
                'tier' => $source['enabled'] && $source['analysis_enabled'] ? 'core' : 'candidate',
                'category' => 'programming',
                'sort_order' => ($index + 1) * 10,
            ]);
        }
    }

    private function enableSelectionForTests(): void
    {
        config([
            'digestpipe.selection' => [
                'enabled' => true,
                'default_score' => 0,
                'analysis_threshold' => 10,
                'skip_threshold' => -50,
            ],
        ]);

        SelectionKeyword::query()->delete();
        $this->createSelectionKeyword('Laravel', 'positive', 15, 10);
        $this->createSelectionKeyword('AWS', 'positive', 12, 20);
        $this->createSelectionKeyword('PHP', 'positive', 5, 30);
        $this->createSelectionKeyword('crypto', 'negative', -100, 40);
        $this->createSelectionKeyword('blockchain', 'negative', -100, 50);
        $this->createSelectionKeyword('token', 'negative', -10, 60);
    }

    private function createSelectionKeyword(string $keyword, string $type, int $score, int $sortOrder): void
    {
        SelectionKeyword::query()->create([
            'keyword' => $keyword,
            'type' => $type,
            'score' => $score,
            'enabled' => true,
            'locale' => 'any',
            'category' => null,
            'sort_order' => $sortOrder,
        ]);
    }
}
