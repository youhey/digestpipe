<?php

namespace Tests\Feature;

use App\Models\DigestItem;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use JsonException;
use Tests\TestCase;

/**
 * @internal
 */
class SelectionReportCommandTest extends TestCase
{
    use RefreshDatabase;

    private int $digestItemSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-25T12:00:00Z'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function testCommandRunsWithNoRecords(): void
    {
        $exitCode = Artisan::call('digestpipe:selection:report');

        self::assertSame(0, $exitCode);

        $document = $this->reportJson();
        $summary = $this->summary($document);

        self::assertSame(0, $summary['total']);
        self::assertSame(0, $summary['selected']);
        self::assertSame(0, $summary['skipped']);
        self::assertSame(0, $summary['pending']);
        self::assertSame(0, $summary['other']);
        self::assertNull($summary['average_score']);
        self::assertSame([], $document['sources']);
        self::assertSame([], $this->keywordRows($document, 'positive'));
        self::assertSame([], $this->recentRows($document, 'selected'));
    }

    public function testSummaryCountsSelectionStatusesAndScores(): void
    {
        $this->createDigestItem([
            'selection_status' => 'selected',
            'selection_score' => 12,
        ]);
        $this->createDigestItem([
            'selection_status' => 'skipped',
            'selection_score' => -100,
        ]);
        $this->createDigestItem([
            'selection_status' => 'pending',
            'selection_score' => null,
        ]);
        $this->createDigestItem([
            'selection_status' => 'needs_review',
            'selection_score' => 5,
        ]);

        $summary = $this->summary($this->reportJson());

        self::assertSame(4, $summary['total']);
        self::assertSame(1, $summary['selected']);
        self::assertSame(1, $summary['skipped']);
        self::assertSame(1, $summary['pending']);
        self::assertSame(1, $summary['other']);
        self::assertSame(-27.67, $summary['average_score']);
        self::assertSame(-100, $summary['min_score']);
        self::assertSame(12, $summary['max_score']);
    }

    public function testSourceBreakdownGroupsSelectionMetricsBySource(): void
    {
        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'source_name' => 'Hacker News',
            'selection_status' => 'selected',
            'selection_score' => 10,
        ]);
        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'source_name' => 'Hacker News',
            'selection_status' => 'skipped',
            'selection_score' => -20,
        ]);
        $this->createDigestItem([
            'source_key' => 'aws_news',
            'source_name' => 'AWS News',
            'selection_status' => 'pending',
            'selection_score' => null,
        ]);

        $sources = $this->sourceRowsByKey($this->reportJson());

        self::assertSame(2, $sources['hacker_news']['total']);
        self::assertSame(1, $sources['hacker_news']['selected']);
        self::assertSame(1, $sources['hacker_news']['skipped']);
        self::assertEquals(-5.0, $sources['hacker_news']['average_score']);
        self::assertSame(1, $sources['aws_news']['total']);
        self::assertSame(1, $sources['aws_news']['pending']);
    }

    public function testKeywordAggregationUsesSelectionResultJson(): void
    {
        $this->createDigestItem([
            'selection_result' => [
                'matched_good_keywords' => ['Laravel', 'AWS'],
                'matched_bad_keywords' => ['crypto'],
            ],
        ]);
        $this->createDigestItem([
            'selection_result' => [
                'matched_good_keywords' => ['Laravel'],
                'matched_bad_keywords' => ['blockchain'],
            ],
        ]);
        $this->createDigestItem([
            'selection_result' => null,
        ]);
        $this->createDigestItem([
            'selection_result' => [
                'matched_good_keywords' => 'Laravel',
            ],
        ]);

        $document = $this->reportJson();

        self::assertSame([
            ['keyword' => 'Laravel', 'count' => 2],
            ['keyword' => 'AWS', 'count' => 1],
        ], $this->keywordRows($document, 'positive'));
        self::assertSame([
            ['keyword' => 'crypto', 'count' => 1],
            ['keyword' => 'blockchain', 'count' => 1],
        ], $this->keywordRows($document, 'negative'));
    }

    public function testSourceOptionFiltersResults(): void
    {
        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'source_name' => 'Hacker News',
            'selection_status' => 'selected',
        ]);
        $this->createDigestItem([
            'source_key' => 'aws_news',
            'source_name' => 'AWS News',
            'selection_status' => 'skipped',
        ]);

        $document = $this->reportJson([
            '--source' => 'hacker_news',
        ]);
        $summary = $this->summary($document);

        self::assertSame('hacker_news', $this->filters($document)['source']);
        self::assertSame(1, $summary['total']);
        self::assertSame(1, $summary['selected']);
        self::assertSame(['hacker_news'], array_keys($this->sourceRowsByKey($document)));
    }

    public function testHoursOptionFiltersBySelectionTimestampWithFetchedFallback(): void
    {
        $this->createDigestItem([
            'selection_evaluated_at' => CarbonImmutable::parse('2026-05-25T11:00:00Z'),
            'fetched_at' => CarbonImmutable::parse('2026-05-23T11:00:00Z'),
        ]);
        $this->createDigestItem([
            'selection_evaluated_at' => null,
            'fetched_at' => CarbonImmutable::parse('2026-05-25T11:30:00Z'),
        ]);
        $this->createDigestItem([
            'selection_evaluated_at' => CarbonImmutable::parse('2026-05-23T12:00:00Z'),
            'fetched_at' => CarbonImmutable::parse('2026-05-25T11:45:00Z'),
        ]);

        $summary = $this->summary($this->reportJson([
            '--hours' => 2,
        ]));

        self::assertSame(2, $summary['total']);
    }

    public function testRecentSelectedAndSkippedItemsRespectLimit(): void
    {
        $oldSelected = $this->createDigestItem([
            'selection_status' => 'selected',
            'selection_evaluated_at' => CarbonImmutable::parse('2026-05-25T10:00:00Z'),
        ]);
        $newSelected = $this->createDigestItem([
            'selection_status' => 'selected',
            'selection_evaluated_at' => CarbonImmutable::parse('2026-05-25T11:00:00Z'),
        ]);
        $skipped = $this->createDigestItem([
            'selection_status' => 'skipped',
            'selection_score' => -100,
            'selection_reason' => 'below_skip_threshold',
        ]);

        $document = $this->reportJson([
            '--limit' => 1,
        ]);

        self::assertSame($newSelected->id, $this->recentRows($document, 'selected')[0]['id']);
        self::assertNotSame($oldSelected->id, $this->recentRows($document, 'selected')[0]['id']);
        self::assertSame($skipped->id, $this->recentRows($document, 'skipped')[0]['id']);
    }

    public function testJsonFormatReturnsValidJson(): void
    {
        $this->createDigestItem();

        $document = $this->reportJson();

        self::assertArrayHasKey('period', $document);
        self::assertArrayHasKey('filters', $document);
        self::assertArrayHasKey('summary', $document);
        self::assertArrayHasKey('sources', $document);
        self::assertArrayHasKey('keywords', $document);
        self::assertArrayHasKey('recent', $document);
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
            'external_id' => 'selection-report-' . $sequence,
            'identity_hash' => hash('sha256', 'selection-report-' . $sequence),
            'source_url' => 'https://example.test/articles/' . $sequence,
            'discussion_url' => null,
            'title' => 'Example title ' . $sequence,
            'excerpt' => 'Example excerpt ' . $sequence,
            'published_at' => CarbonImmutable::parse('2026-05-25T10:00:00Z'),
            'fetched_at' => CarbonImmutable::parse('2026-05-25T10:05:00Z'),
            'content_hash' => hash('sha256', 'selection-report-content-' . $sequence),
            'selection_status' => 'selected',
            'selection_score' => 10,
            'selection_reason' => 'above_analysis_threshold',
            'selection_result' => [
                'score' => 10,
                'status' => 'selected',
                'matched_good_keywords' => ['Laravel'],
                'matched_bad_keywords' => [],
                'reason' => 'above_analysis_threshold',
            ],
            'selection_evaluated_at' => CarbonImmutable::parse('2026-05-25T10:10:00Z'),
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

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function reportJson(array $options = []): array
    {
        $exitCode = Artisan::call('digestpipe:selection:report', array_merge([
            '--format' => 'json',
        ], $options));

        self::assertSame(0, $exitCode);

        return $this->decodeJsonObject(Artisan::output());
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function decodeJsonObject(string $json): array
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return array{total: int, selected: int, skipped: int, pending: int, other: int, average_score: float|null, min_score: int|null, max_score: int|null}
     */
    private function summary(array $document): array
    {
        $summary = $document['summary'] ?? null;

        self::assertIsArray($summary);

        /** @var array{total: int, selected: int, skipped: int, pending: int, other: int, average_score: float|null, min_score: int|null, max_score: int|null} $summary */
        return $summary;
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return array{source: string|null}
     */
    private function filters(array $document): array
    {
        $filters = $document['filters'] ?? null;

        self::assertIsArray($filters);

        /** @var array{source: string|null} $filters */
        return $filters;
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return array<string, array{source_key: string, total: int, selected: int, skipped: int, pending: int, other: int, average_score: float|null, min_score: int|null, max_score: int|null}>
     */
    private function sourceRowsByKey(array $document): array
    {
        $rows = $document['sources'] ?? null;

        self::assertIsArray($rows);

        $mapped = [];
        foreach ($rows as $row) {
            self::assertIsArray($row);
            self::assertIsString($row['source_key'] ?? null);

            /** @var array{source_key: string, total: int, selected: int, skipped: int, pending: int, other: int, average_score: float|null, min_score: int|null, max_score: int|null} $row */
            $mapped[$row['source_key']] = $row;
        }

        return $mapped;
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return list<array{keyword: string, count: int}>
     */
    private function keywordRows(array $document, string $type): array
    {
        $keywords = $document['keywords'] ?? null;

        self::assertIsArray($keywords);

        $rows = $keywords[$type] ?? null;

        self::assertIsArray($rows);

        /** @var list<array{keyword: string, count: int}> $rows */
        return $rows;
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return list<array{id: int, source_key: string, selection_score: int|null, title: string, selection_reason: string|null}>
     */
    private function recentRows(array $document, string $status): array
    {
        $recent = $document['recent'] ?? null;

        self::assertIsArray($recent);

        $rows = $recent[$status] ?? null;

        self::assertIsArray($rows);

        /** @var list<array{id: int, source_key: string, selection_score: int|null, title: string, selection_reason: string|null}> $rows */
        return $rows;
    }
}
