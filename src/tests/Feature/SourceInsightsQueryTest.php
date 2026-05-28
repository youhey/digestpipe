<?php

namespace Tests\Feature;

use App\Admin\SourceInsightsQuery;
use App\Filament\Widgets\SourceInsightsTableWidget;
use App\Insights\SourceInsightsExporter;
use App\Models\DigestItem;
use App\Models\FeedSource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use ReflectionMethod;
use Tests\TestCase;

/**
 * @internal
 */
class SourceInsightsQueryTest extends TestCase
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

    public function testSourceInsightsCalculatesRatesAndAverageScore(): void
    {
        $this->createFeedSource('hacker_news');
        $this->createFeedSource('aws_news');
        $this->createFeedSource('empty_source');

        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'selection_status' => 'selected',
            'selection_score' => 10,
            'article_content_status' => 'completed',
            'analysis_status' => 'completed',
            'analyzed_at' => CarbonImmutable::now()->subMinutes(1),
        ]);
        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'selection_status' => 'skipped',
            'selection_score' => -20,
            'article_content_status' => 'failed',
            'analysis_status' => 'pending',
            'article_content_fetched_at' => CarbonImmutable::now()->subMinutes(2),
        ]);
        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'selection_status' => 'needs_content',
            'selection_score' => null,
            'article_content_status' => 'queued',
            'analysis_status' => 'failed',
            'updated_at' => CarbonImmutable::now()->subMinutes(3),
        ]);
        $this->createDigestItem([
            'source_key' => 'aws_news',
            'selection_status' => 'selected',
            'selection_score' => 50,
            'analysis_status' => 'completed',
            'analyzed_at' => CarbonImmutable::now()->subMinutes(4),
        ]);

        $report = app(SourceInsightsQuery::class)->report(sort: 'total');
        $hackerNews = $this->sourceRow($report['sources'], 'hacker_news');
        $emptySource = $this->sourceRow($report['sources'], 'empty_source');

        self::assertSame(3, $hackerNews['total']);
        self::assertSame(33.33, $hackerNews['selected_rate']);
        self::assertSame(33.33, $hackerNews['skipped_rate']);
        self::assertSame(33.33, $hackerNews['pending_rate']);
        self::assertSame(33.33, $hackerNews['analysis_completed_rate']);
        self::assertSame(66.67, $hackerNews['failure_rate']);
        self::assertSame(-5.0, $hackerNews['average_score']);
        self::assertSame(0, $emptySource['total']);
        self::assertSame(0.0, $emptySource['selected_rate']);
    }

    public function testSourceInsightsDaysOptionLimitsRows(): void
    {
        $this->createFeedSource('hacker_news');

        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'selection_status' => 'selected',
            'selection_score' => 10,
            'selection_evaluated_at' => CarbonImmutable::now()->subHours(12),
        ]);
        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'selection_status' => 'skipped',
            'selection_score' => -10,
            'selection_evaluated_at' => CarbonImmutable::now()->subDays(2),
            'fetched_at' => CarbonImmutable::now()->subDays(2),
            'updated_at' => CarbonImmutable::now()->subDays(2),
        ]);

        $row = $this->sourceRow(app(SourceInsightsQuery::class)->report(days: 1)['sources'], 'hacker_news');

        self::assertSame(1, $row['total']);
        self::assertSame(100.0, $row['selected_rate']);
        self::assertSame(0.0, $row['skipped_rate']);
    }

    public function testSourceInsightsCommandOutputsRows(): void
    {
        $this->createFeedSource('hacker_news');
        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'selection_status' => 'selected',
            'selection_score' => 10,
            'analysis_status' => 'completed',
        ]);

        self::assertSame(0, Artisan::call('digestpipe:sources:insights', [
            '--days' => 7,
            '--sort' => 'selected-rate',
        ]));

        $output = Artisan::output();

        self::assertStringContainsString('Source Insights', $output);
        self::assertStringContainsString('hacker_news', $output);
        self::assertStringContainsString('100.00%', $output);
    }

    public function testSourceInsightsCommandRejectsInvalidOptions(): void
    {
        self::assertSame(2, Artisan::call('digestpipe:sources:insights', [
            '--days' => 0,
        ]));
        self::assertSame(2, Artisan::call('digestpipe:sources:insights', [
            '--sort' => 'unknown',
        ]));
    }

    public function testAuthorizedAdminCanAccessSourceInsightsPage(): void
    {
        config(['digestpipe.admin.allowed_emails' => ['admin@example.test']]);
        $user = User::factory()->create(['email' => 'admin@example.test']);

        $this->actingAs($user)
            ->get('/admin/source-insights')
            ->assertOk()
            ->assertSee('Source Insights')
            ->assertSee('Export Insights');
    }

    public function testSourceInsightsTableUsesReadableLabelsAndDefaultPageSize(): void
    {
        $method = new ReflectionMethod(SourceInsightsTableWidget::class, 'getViewData');
        $method->setAccessible(true);

        /** @var array<string, mixed> $data */
        $data = $method->invoke(new SourceInsightsTableWidget());

        self::assertSame(50, $data['defaultPerPage']);
        self::assertSame([10, 25, 50, 100], $data['perPageOptions']);
        self::assertIsArray($data['columnLabels']);

        /** @var array<string, string> $columnLabels */
        $columnLabels = $data['columnLabels'];

        self::assertSame('Source', $columnLabels['source_key']);
        self::assertSame('Selected Rate', $columnLabels['selected_rate']);
        self::assertSame('Average Selection Score', $columnLabels['average_selection_score']);
    }

    public function testSourceInsightsExporterBuildsMarkdownDownload(): void
    {
        $this->createFeedSource('hacker_news');
        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'selection_status' => 'selected',
            'selection_score' => 10,
            'analysis_status' => 'completed',
        ]);

        $result = app(SourceInsightsExporter::class)->export(days: 7, sort: 'total');

        self::assertSame('text/markdown; charset=UTF-8', $result->mimeType);
        self::assertStringStartsWith('digestpipe-source-insights-20260528-120000', $result->filename);
        self::assertStringContainsString('# digestpipe Source Insights Export', $result->content);
        self::assertStringContainsString('## Source Comparison', $result->content);
        self::assertStringContainsString('hacker_news', $result->content);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, mixed>
     */
    private function sourceRow(array $rows, string $sourceKey): array
    {
        foreach ($rows as $row) {
            if ($row['source_key'] === $sourceKey) {
                return $row;
            }
        }

        self::fail('Source row was not found: ' . $sourceKey);
    }

    private function createFeedSource(string $key): FeedSource
    {
        return FeedSource::query()->create([
            'key' => $key,
            'name' => str_replace('_', ' ', $key),
            'url' => 'https://example.test/' . $key . '.xml',
            'language' => 'en',
            'enabled' => true,
            'analysis_enabled' => true,
            'tier' => 'core',
            'category' => 'programming',
            'sort_order' => 10 + $this->digestItemSequence,
        ]);
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
            'external_id' => 'source-insights-' . $sequence,
            'identity_hash' => hash('sha256', 'source-insights-' . $sequence),
            'source_url' => 'https://example.test/articles/' . $sequence,
            'discussion_url' => null,
            'title' => 'Example title ' . $sequence,
            'excerpt' => 'Example excerpt ' . $sequence,
            'published_at' => CarbonImmutable::now()->subMinutes(40),
            'fetched_at' => CarbonImmutable::now()->subMinutes(35),
            'content_hash' => hash('sha256', 'source-insights-content-' . $sequence),
            'selection_status' => 'pending',
            'selection_score' => null,
            'selection_reason' => null,
            'selection_result' => [
                'matched_good_keywords' => [],
                'matched_bad_keywords' => [],
            ],
            'selection_evaluated_at' => CarbonImmutable::now()->subMinutes(30),
            'article_content_status' => 'pending',
            'article_content_text' => null,
            'article_content_error' => null,
            'article_content_fetched_at' => null,
            'analysis_status' => 'pending',
            'analysis_json' => null,
            'analysis_model' => null,
            'analysis_error' => null,
            'analyzed_at' => null,
        ], $attributes));
    }
}
