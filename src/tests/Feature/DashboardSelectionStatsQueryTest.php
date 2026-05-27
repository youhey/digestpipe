<?php

namespace Tests\Feature;

use App\Admin\DashboardSelectionStatsQuery;
use App\Models\DigestItem;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @internal
 */
class DashboardSelectionStatsQueryTest extends TestCase
{
    use RefreshDatabase;

    private int $digestItemSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-27T12:00:00Z'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function testEmptyDatabaseReturnsZeroDashboardCounts(): void
    {
        $report = app(DashboardSelectionStatsQuery::class)->report();

        self::assertSame(0, $report['summary']['total']);
        self::assertSame(0, $report['summary']['selected']);
        self::assertSame(0, $report['summary']['skipped']);
        self::assertSame(0, $report['summary']['pending']);
        self::assertSame(0, $report['summary']['other']);
        self::assertSame([], $report['sources']);
        self::assertSame([], $report['keywords']['positive']);
        self::assertSame([], $report['keywords']['negative']);
        self::assertSame([], $report['recent']['selected']);
        self::assertSame([], $report['recent']['skipped']);
    }

    public function testDashboardReportAggregatesSelectionStatusSourceAndKeywords(): void
    {
        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'selection_status' => 'selected',
            'selection_score' => 15,
            'selection_result' => [
                'matched_good_keywords' => ['Laravel', 'AWS'],
                'matched_bad_keywords' => [],
            ],
        ]);
        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'selection_status' => 'skipped',
            'selection_score' => -100,
            'selection_result' => [
                'matched_good_keywords' => [],
                'matched_bad_keywords' => ['crypto'],
            ],
        ]);
        $this->createDigestItem([
            'source_key' => 'aws_news',
            'selection_status' => 'needs_content',
            'selection_score' => 0,
        ]);
        $this->createDigestItem([
            'source_key' => 'aws_news',
            'selection_status' => 'failed_selection',
            'selection_score' => null,
        ]);
        $this->createDigestItem([
            'source_key' => 'old_source',
            'selection_status' => 'selected',
            'selection_score' => 999,
            'selection_evaluated_at' => CarbonImmutable::now()->subDays(8),
            'fetched_at' => CarbonImmutable::now()->subDays(8),
        ]);

        $report = app(DashboardSelectionStatsQuery::class)->report();

        self::assertSame([
            'total' => 4,
            'selected' => 1,
            'skipped' => 1,
            'pending' => 1,
            'other' => 1,
            'average_score' => -28.33,
        ], $report['summary']);
        self::assertSame([
            [
                'source_key' => 'aws_news',
                'total' => 2,
                'selected' => 0,
                'skipped' => 0,
                'pending' => 1,
                'other' => 1,
                'average_score' => 0.0,
            ],
            [
                'source_key' => 'hacker_news',
                'total' => 2,
                'selected' => 1,
                'skipped' => 1,
                'pending' => 0,
                'other' => 0,
                'average_score' => -42.5,
            ],
        ], $report['sources']);
        self::assertSame([
            ['keyword' => 'Laravel', 'count' => 1],
            ['keyword' => 'AWS', 'count' => 1],
        ], $report['keywords']['positive']);
        self::assertSame([
            ['keyword' => 'crypto', 'count' => 1],
        ], $report['keywords']['negative']);
    }

    public function testRecentSelectedAndSkippedItemsAreLimited(): void
    {
        for ($index = 1; $index <= 6; ++$index) {
            $this->createDigestItem([
                'selection_status' => 'selected',
                'selection_score' => $index,
                'title' => 'Selected ' . $index,
                'selection_evaluated_at' => CarbonImmutable::now()->subMinutes($index),
            ]);
            $this->createDigestItem([
                'selection_status' => 'skipped',
                'selection_score' => -$index,
                'title' => 'Skipped ' . $index,
                'selection_evaluated_at' => CarbonImmutable::now()->subMinutes($index),
            ]);
        }

        $report = app(DashboardSelectionStatsQuery::class)->report(recentLimit: 5);

        self::assertCount(5, $report['recent']['selected']);
        self::assertCount(5, $report['recent']['skipped']);
        self::assertSame('Selected 1', $report['recent']['selected'][0]['title']);
        self::assertSame('Skipped 1', $report['recent']['skipped'][0]['title']);
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
            'external_id' => 'dashboard-' . $sequence,
            'identity_hash' => hash('sha256', 'dashboard-' . $sequence),
            'source_url' => 'https://example.test/articles/' . $sequence,
            'discussion_url' => null,
            'title' => 'Example title ' . $sequence,
            'excerpt' => 'Example excerpt ' . $sequence,
            'published_at' => CarbonImmutable::now()->subMinutes(30),
            'fetched_at' => CarbonImmutable::now()->subMinutes(20),
            'content_hash' => hash('sha256', 'dashboard-content-' . $sequence),
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
