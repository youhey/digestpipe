<?php

namespace Tests\Feature;

use App\Insights\InsightsExportOptions;
use App\Insights\SelectionInsightsExporter;
use App\Models\DigestItem;
use App\Models\FeedSource;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * @internal
 */
class InsightsExportCommandTest extends TestCase
{
    use RefreshDatabase;

    private int $digestItemSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-28T03:00:00Z'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function testServiceExportsMarkdownWithExpectedHeadings(): void
    {
        $this->createFeedSource('hacker_news');
        $this->createDigestItem([
            'selection_status' => 'selected',
            'selection_score' => 12,
            'selection_result' => [
                'matched_good_keywords' => ['Laravel'],
                'matched_bad_keywords' => [],
            ],
        ]);

        $result = app(SelectionInsightsExporter::class)->export(InsightsExportOptions::make());

        self::assertSame('text/markdown; charset=UTF-8', $result->mimeType);
        self::assertStringStartsWith('digestpipe-insights-20260528-030000', $result->filename);
        self::assertStringContainsString('# digestpipe Insights Export', $result->content);
        self::assertStringContainsString('## Suggested Analysis Prompt', $result->content);
        self::assertStringContainsString('## Summary', $result->content);
        self::assertStringContainsString('## Source Breakdown', $result->content);
        self::assertStringContainsString('## Top Positive Keywords', $result->content);
        self::assertStringContainsString('## Top Negative Keywords', $result->content);
        self::assertStringContainsString('## Recent Skipped Items', $result->content);
        self::assertStringContainsString('## Recent Selected Items', $result->content);
    }

    public function testServiceExportsSummarySourceBreakdownAndKeywords(): void
    {
        $this->createFeedSource('hacker_news');
        $this->createFeedSource('aws_news');
        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'source_name' => 'Hacker News',
            'selection_status' => 'selected',
            'selection_score' => 12,
            'selection_result' => [
                'matched_good_keywords' => ['Laravel', 'AWS'],
                'matched_bad_keywords' => ['crypto'],
            ],
        ]);
        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'source_name' => 'Hacker News',
            'selection_status' => 'skipped',
            'selection_score' => -20,
            'selection_result' => [
                'matched_good_keywords' => ['Laravel'],
                'matched_bad_keywords' => ['blockchain'],
            ],
        ]);
        $this->createDigestItem([
            'source_key' => 'aws_news',
            'source_name' => 'AWS News',
            'selection_status' => 'pending',
            'selection_score' => null,
            'selection_result' => null,
        ]);

        $markdown = app(SelectionInsightsExporter::class)->export(InsightsExportOptions::make())->content;

        self::assertStringContainsString('| total_digest_items | 3 |', $markdown);
        self::assertStringContainsString('| selected | 1 |', $markdown);
        self::assertStringContainsString('| skipped | 1 |', $markdown);
        self::assertStringContainsString('| pending | 1 |', $markdown);
        self::assertStringContainsString('| average_selection_score | -4 |', $markdown);
        self::assertStringContainsString('| hacker_news | 2 | 1 | 1 | 0 | 0 | -4 |', $markdown);
        self::assertStringContainsString('| aws_news | 1 | 0 | 0 | 1 | 0 | n/a |', $markdown);
        self::assertStringContainsString('| Laravel | 2 |', $markdown);
        self::assertStringContainsString('| AWS | 1 |', $markdown);
        self::assertStringContainsString('| blockchain | 1 |', $markdown);
        self::assertStringContainsString('| crypto | 1 |', $markdown);
    }

    public function testRecentItemsRespectSampleLimit(): void
    {
        $this->createFeedSource('hacker_news');
        $oldSelected = $this->createDigestItem([
            'title' => 'Old selected item',
            'selection_status' => 'selected',
            'selection_evaluated_at' => CarbonImmutable::parse('2026-05-27T00:00:00Z'),
        ]);
        $newSelected = $this->createDigestItem([
            'title' => 'New selected item',
            'selection_status' => 'selected',
            'selection_evaluated_at' => CarbonImmutable::parse('2026-05-28T01:00:00Z'),
        ]);
        $skipped = $this->createDigestItem([
            'title' => 'Skipped item',
            'selection_status' => 'skipped',
            'selection_score' => -100,
            'selection_reason' => 'below_skip_threshold',
        ]);

        $markdown = app(SelectionInsightsExporter::class)
            ->export(InsightsExportOptions::make(sampleLimit: 1))
            ->content;

        self::assertStringContainsString('| ' . $newSelected->id . ' | hacker_news | 10 | above_analysis_threshold | New selected item |', $markdown);
        self::assertStringNotContainsString('| ' . $oldSelected->id . ' | hacker_news | 10 | above_analysis_threshold | Old selected item |', $markdown);
        self::assertStringContainsString('| ' . $skipped->id . ' | hacker_news | -100 | below_skip_threshold | Skipped item |', $markdown);
    }

    public function testSourceFilterLimitsAllSections(): void
    {
        $this->createFeedSource('hacker_news');
        $this->createFeedSource('aws_news');
        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'source_name' => 'Hacker News',
            'title' => 'Hacker News item',
            'selection_result' => [
                'matched_good_keywords' => ['Laravel'],
                'matched_bad_keywords' => [],
            ],
        ]);
        $this->createDigestItem([
            'source_key' => 'aws_news',
            'source_name' => 'AWS News',
            'title' => 'AWS item',
            'selection_result' => [
                'matched_good_keywords' => ['CloudWatch'],
                'matched_bad_keywords' => [],
            ],
        ]);

        $markdown = app(SelectionInsightsExporter::class)
            ->export(InsightsExportOptions::make(source: 'hacker_news'))
            ->content;

        self::assertStringContainsString('Source filter: hacker_news', $markdown);
        self::assertStringContainsString('| hacker_news | 1 |', $markdown);
        self::assertStringContainsString('| Laravel | 1 |', $markdown);
        self::assertStringContainsString('Hacker News item', $markdown);
        self::assertStringNotContainsString('| aws_news |', $markdown);
        self::assertStringNotContainsString('CloudWatch', $markdown);
        self::assertStringNotContainsString('AWS item', $markdown);
    }

    public function testEmptyDatabaseProducesUsefulMarkdown(): void
    {
        $markdown = app(SelectionInsightsExporter::class)->export(InsightsExportOptions::make())->content;

        self::assertStringContainsString('# digestpipe Insights Export', $markdown);
        self::assertStringContainsString('| total_digest_items | 0 |', $markdown);
        self::assertStringContainsString('| average_selection_score | n/a |', $markdown);
        self::assertStringContainsString('## Recent Selected Items', $markdown);
    }

    public function testExportDoesNotIncludeRawArticleContent(): void
    {
        $this->createFeedSource('hacker_news');
        $this->createDigestItem([
            'article_content_text' => 'Raw article content must not be exported.',
        ]);

        $markdown = app(SelectionInsightsExporter::class)->export(InsightsExportOptions::make())->content;

        self::assertStringNotContainsString('Raw article content must not be exported.', $markdown);
    }

    public function testCommandWritesMarkdownToStdout(): void
    {
        $this->createFeedSource('hacker_news');
        $this->createDigestItem();

        $exitCode = Artisan::call('digestpipe:insights:export', [
            '--days' => 7,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('# digestpipe Insights Export', Artisan::output());
    }

    public function testCommandWritesMarkdownToFile(): void
    {
        $this->createFeedSource('hacker_news');
        $this->createDigestItem();
        $path = sys_get_temp_dir() . '/digestpipe-insights-command-test.md';

        if (is_file($path)) {
            unlink($path);
        }

        $exitCode = Artisan::call('digestpipe:insights:export', [
            '--days' => 7,
            '--output' => $path,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertFileExists($path);
        self::assertStringContainsString('# digestpipe Insights Export', (string) file_get_contents($path));

        unlink($path);
    }

    public function testCommandRejectsInvalidSource(): void
    {
        $this->createFeedSource('hacker_news');

        $exitCode = Artisan::call('digestpipe:insights:export', [
            '--source' => 'missing_source',
        ]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('Unknown source: missing_source', Artisan::output());
    }

    public function testCommandRejectsUnsupportedFormat(): void
    {
        $exitCode = Artisan::call('digestpipe:insights:export', [
            '--format' => 'json',
        ]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('The --format option must be markdown.', Artisan::output());
    }

    private function createFeedSource(string $key): FeedSource
    {
        return FeedSource::query()->create([
            'key' => $key,
            'name' => $key,
            'url' => 'https://feeds.example.test/' . $key . '.xml',
            'language' => 'en',
            'enabled' => true,
            'analysis_enabled' => true,
            'tier' => 'core',
            'category' => 'programming',
            'sort_order' => 10,
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
            'external_id' => 'insights-export-' . $sequence,
            'identity_hash' => hash('sha256', 'insights-export-' . $sequence),
            'source_url' => 'https://example.test/articles/' . $sequence,
            'discussion_url' => null,
            'title' => 'Example title ' . $sequence,
            'excerpt' => 'Example excerpt ' . $sequence,
            'published_at' => CarbonImmutable::parse('2026-05-27T10:00:00Z'),
            'fetched_at' => CarbonImmutable::parse('2026-05-27T10:05:00Z'),
            'content_hash' => hash('sha256', 'insights-export-content-' . $sequence),
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
            'selection_evaluated_at' => CarbonImmutable::parse('2026-05-27T10:10:00Z'),
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
