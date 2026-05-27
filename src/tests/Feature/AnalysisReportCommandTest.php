<?php

namespace Tests\Feature;

use App\Models\DigestItem;
use App\Models\FeedSource;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * @internal
 */
class AnalysisReportCommandTest extends TestCase
{
    use RefreshDatabase;

    private int $digestItemSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFeedSource('hacker_news', 'Hacker News', 10);
        $this->createFeedSource('aws_news', 'AWS News', 20);
    }

    public function testReportsOverallContentTypeCounts(): void
    {
        $this->createDigestItem(['analysis_json' => $this->analysisJson('news/article')]);
        $this->createDigestItem(['analysis_json' => $this->analysisJson('news/article')]);
        $this->createDigestItem(['analysis_json' => $this->analysisJson('blog post')]);
        $this->createDigestItem([
            'analysis_status' => 'pending',
            'analysis_json' => $this->analysisJson('pending_type'),
        ]);
        $this->createDigestItem([
            'analysis_status' => 'completed',
            'analysis_json' => null,
        ]);

        $output = $this->runReport();

        self::assertStringContainsString('Content type breakdown', $output);
        self::assertMatchesRegularExpression('/news\/article\s+\|\s+2/', $output);
        self::assertMatchesRegularExpression('/blog post\s+\|\s+1/', $output);
        self::assertStringNotContainsString('pending_type', $output);
    }

    public function testReportsSourceLevelContentTypeCounts(): void
    {
        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'source_name' => 'Hacker News',
            'analysis_json' => $this->analysisJson('blog post'),
        ]);
        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'source_name' => 'Hacker News',
            'analysis_json' => $this->analysisJson('blog post'),
        ]);
        $this->createDigestItem([
            'source_key' => 'aws_news',
            'source_name' => 'AWS News',
            'analysis_json' => $this->analysisJson('announcement'),
        ]);

        $output = $this->runReport();

        self::assertStringContainsString('Content type by source', $output);
        self::assertMatchesRegularExpression('/hacker_news\s+\|\s+blog post\s+\|\s+2/', $output);
        self::assertMatchesRegularExpression('/aws_news\s+\|\s+announcement\s+\|\s+1/', $output);
    }

    public function testListsRecentSamples(): void
    {
        $older = $this->createDigestItem([
            'title' => 'Older analyzed item',
            'analysis_json' => $this->analysisJson('news_report'),
            'analyzed_at' => CarbonImmutable::parse('2026-05-25T10:00:00Z'),
        ]);
        $newer = $this->createDigestItem([
            'title' => 'Newer analyzed item',
            'analysis_json' => $this->analysisJson('announcement'),
            'analyzed_at' => CarbonImmutable::parse('2026-05-25T11:00:00Z'),
        ]);

        $output = $this->runReport(['--limit' => 1]);

        self::assertStringContainsString('Recent samples', $output);
        self::assertStringContainsString((string) $newer->id, $output);
        self::assertStringContainsString('Newer analyzed item', $output);
        self::assertStringNotContainsString((string) $older->id, $output);
        self::assertStringNotContainsString('Older analyzed item', $output);
    }

    public function testSourceOptionFiltersAllSections(): void
    {
        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'source_name' => 'Hacker News',
            'analysis_json' => $this->analysisJson('blog post'),
        ]);
        $this->createDigestItem([
            'source_key' => 'aws_news',
            'source_name' => 'AWS News',
            'analysis_json' => $this->analysisJson('announcement'),
        ]);

        $output = $this->runReport(['--source' => 'hacker_news']);

        self::assertStringContainsString('hacker_news', $output);
        self::assertStringContainsString('blog post', $output);
        self::assertStringNotContainsString('aws_news', $output);
        self::assertStringNotContainsString('announcement', $output);
    }

    public function testEmptyAnalyzedDataPrintsUsefulMessage(): void
    {
        $this->createDigestItem([
            'analysis_status' => 'pending',
            'analysis_json' => $this->analysisJson('news_article'),
        ]);

        $output = $this->runReport();

        self::assertStringContainsString('No completed analysis records found.', $output);
    }

    public function testUnknownSourceFailsClearly(): void
    {
        $exitCode = Artisan::call('digestpipe:analysis:report', [
            '--source' => 'missing_source',
        ]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Unknown source: missing_source', Artisan::output());
    }

    /**
     * @param array<string, mixed> $options
     */
    private function runReport(array $options = []): string
    {
        $exitCode = Artisan::call('digestpipe:analysis:report', $options);

        self::assertSame(0, $exitCode);

        return Artisan::output();
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
            'external_id' => 'analysis-report-' . $sequence,
            'identity_hash' => hash('sha256', 'analysis-report-' . $sequence),
            'source_url' => 'https://example.test/articles/' . $sequence,
            'discussion_url' => null,
            'title' => 'Example title ' . $sequence,
            'excerpt' => 'Example excerpt ' . $sequence,
            'published_at' => CarbonImmutable::parse('2026-05-25T10:00:00Z'),
            'fetched_at' => CarbonImmutable::parse('2026-05-25T10:05:00Z'),
            'content_hash' => hash('sha256', 'analysis-report-content-' . $sequence),
            'selection_status' => 'selected',
            'selection_score' => 10,
            'selection_reason' => 'above_analysis_threshold',
            'selection_result' => null,
            'selection_evaluated_at' => CarbonImmutable::parse('2026-05-25T10:10:00Z'),
            'article_content_status' => 'completed',
            'article_content_text' => 'Example article content.',
            'article_content_error' => null,
            'analysis_status' => 'completed',
            'analysis_json' => $this->analysisJson('news_article'),
            'analysis_model' => 'gpt-test',
            'analysis_error' => null,
            'analyzed_at' => CarbonImmutable::parse('2026-05-25T10:20:00Z'),
        ], $attributes));
    }

    private function createFeedSource(string $key, string $name, int $sortOrder): void
    {
        FeedSource::query()->create([
            'key' => $key,
            'name' => $name,
            'url' => 'https://feeds.example.test/' . $key . '.xml',
            'language' => 'en',
            'enabled' => true,
            'analysis_enabled' => true,
            'tier' => 'core',
            'category' => 'programming',
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function analysisJson(string $contentType): array
    {
        return [
            'schema_version' => '1.0',
            'source_language' => 'en',
            'title' => [
                'original' => 'Example title',
                'normalized' => 'Example title',
            ],
            'content' => [
                'brief' => 'Example brief',
                'detailed_summary' => 'Example detailed summary',
                'key_points' => ['Example point'],
                'background' => null,
                'why_it_matters' => null,
                'limitations' => null,
            ],
            'classification' => [
                'content_type' => $contentType,
                'topics' => ['general'],
                'entities' => [],
                'importance' => 3,
                'confidence' => 0.8,
            ],
        ];
    }
}
