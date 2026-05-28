<?php

namespace Tests\Feature;

use App\Admin\PipelineHealthStatsQuery;
use App\Models\DigestItem;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @internal
 */
class PipelineHealthStatsQueryTest extends TestCase
{
    use RefreshDatabase;

    private int $digestItemSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-28T12:00:00Z'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function testEmptyDatabaseReturnsZeroPipelineHealthCounts(): void
    {
        $report = app(PipelineHealthStatsQuery::class)->report();

        self::assertSame([], $report['content_statuses']);
        self::assertSame([], $report['analysis_statuses']);
        self::assertSame([
            'content_failed' => 0,
            'analysis_failed' => 0,
            'content_active' => 0,
            'analysis_active' => 0,
            'analysis_completed' => 0,
        ], $report['kpis']);
        self::assertSame([
            'latest_digest_item_created_at' => null,
            'latest_feed_fetched_at' => null,
            'latest_article_content_fetched_at' => null,
            'latest_analysis_completed_at' => null,
        ], $report['latest']);
        self::assertSame([], $report['recent_failed']);
    }

    public function testPipelineHealthReportAggregatesStatusesAndKpis(): void
    {
        $this->createDigestItem([
            'article_content_status' => 'failed',
            'article_content_error' => 'Content extraction failed with a long but safe operational error message.',
            'analysis_status' => 'pending',
            'selection_evaluated_at' => CarbonImmutable::now()->subMinutes(1),
        ]);
        $this->createDigestItem([
            'article_content_status' => 'completed',
            'article_content_fetched_at' => CarbonImmutable::now()->subMinutes(20),
            'analysis_status' => 'failed',
            'analysis_error' => 'Analysis provider returned invalid JSON.',
            'selection_evaluated_at' => CarbonImmutable::now()->subMinutes(2),
        ]);
        $this->createDigestItem([
            'article_content_status' => 'queued',
            'analysis_status' => 'queued',
            'selection_evaluated_at' => CarbonImmutable::now()->subMinutes(3),
        ]);
        $this->createDigestItem([
            'article_content_status' => 'processing',
            'analysis_status' => 'processing',
            'selection_evaluated_at' => CarbonImmutable::now()->subMinutes(4),
        ]);
        $this->createDigestItem([
            'article_content_status' => 'completed',
            'article_content_fetched_at' => CarbonImmutable::now()->subMinutes(5),
            'analysis_status' => 'completed',
            'analyzed_at' => CarbonImmutable::now()->subMinutes(4),
            'selection_evaluated_at' => CarbonImmutable::now()->subMinutes(4),
        ]);
        $this->createDigestItem([
            'article_content_status' => 'failed',
            'analysis_status' => 'failed',
            'fetched_at' => CarbonImmutable::now()->subDays(8),
            'selection_evaluated_at' => CarbonImmutable::now()->subDays(8),
        ]);

        $report = app(PipelineHealthStatsQuery::class)->report();

        self::assertSame([
            'completed' => 2,
            'failed' => 1,
            'processing' => 1,
            'queued' => 1,
        ], $report['content_statuses']);
        self::assertSame([
            'completed' => 1,
            'failed' => 1,
            'pending' => 1,
            'processing' => 1,
            'queued' => 1,
        ], $report['analysis_statuses']);
        self::assertSame([
            'content_failed' => 1,
            'analysis_failed' => 1,
            'content_active' => 2,
            'analysis_active' => 2,
            'analysis_completed' => 1,
        ], $report['kpis']);
        self::assertSame(CarbonImmutable::now()->subMinutes(4)->toJSON(), $report['latest']['latest_analysis_completed_at']);
        self::assertCount(2, $report['recent_failed']);
        self::assertSame('article_content', $report['recent_failed'][0]['failed_stage']);
        self::assertSame('analysis', $report['recent_failed'][1]['failed_stage']);
    }

    public function testRecentFailedItemsAreLimited(): void
    {
        for ($index = 1; $index <= 6; ++$index) {
            $this->createDigestItem([
                'title' => 'Failed ' . $index,
                'article_content_status' => 'failed',
                'article_content_error' => 'Failure ' . $index,
                'selection_evaluated_at' => CarbonImmutable::now()->subMinutes($index),
            ]);
        }

        $report = app(PipelineHealthStatsQuery::class)->report(recentLimit: 5);

        self::assertCount(5, $report['recent_failed']);
        self::assertSame('Failed 1', $report['recent_failed'][0]['title']);
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
            'external_id' => 'pipeline-health-' . $sequence,
            'identity_hash' => hash('sha256', 'pipeline-health-' . $sequence),
            'source_url' => 'https://example.test/articles/' . $sequence,
            'discussion_url' => null,
            'title' => 'Example title ' . $sequence,
            'excerpt' => 'Example excerpt ' . $sequence,
            'published_at' => CarbonImmutable::now()->subMinutes(30),
            'fetched_at' => CarbonImmutable::now()->subMinutes(20),
            'content_hash' => hash('sha256', 'pipeline-health-content-' . $sequence),
            'selection_status' => 'selected',
            'selection_score' => 10,
            'selection_reason' => 'above_analysis_threshold',
            'selection_result' => [
                'matched_good_keywords' => [],
                'matched_bad_keywords' => [],
            ],
            'selection_evaluated_at' => CarbonImmutable::now()->subMinutes(10),
            'article_content_status' => 'pending',
            'article_content_text' => null,
            'article_content_error' => null,
            'analysis_status' => 'pending',
            'analysis_json' => null,
            'analysis_model' => null,
            'analysis_error' => null,
            'analyzed_at' => null,
        ], $attributes));
    }
}
