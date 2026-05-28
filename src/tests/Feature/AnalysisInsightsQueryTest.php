<?php

namespace Tests\Feature;

use App\Admin\AnalysisInsightsQuery;
use App\Insights\AnalysisInsightsExporter;
use App\Models\DigestItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @internal
 */
class AnalysisInsightsQueryTest extends TestCase
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

    public function testEmptyDatabaseReturnsEmptyAnalysisInsights(): void
    {
        $report = app(AnalysisInsightsQuery::class)->report();

        self::assertSame([], $report['content_types']);
        self::assertSame([], $report['content_types_by_source']);
        self::assertSame([], $report['recent_samples']);
        self::assertSame([], $report['low_confidence_items']);
        self::assertSame([
            ['label' => '0.0 - 0.2', 'count' => 0],
            ['label' => '0.3 - 0.5', 'count' => 0],
            ['label' => '0.6 - 0.8', 'count' => 0],
            ['label' => '0.9 - 1.0', 'count' => 0],
        ], $report['confidence_distribution']);
        self::assertSame([
            ['label' => '1', 'count' => 0],
            ['label' => '2', 'count' => 0],
            ['label' => '3', 'count' => 0],
            ['label' => '4', 'count' => 0],
            ['label' => '5', 'count' => 0],
        ], $report['importance_distribution']);
    }

    public function testAnalysisInsightsAggregatesClassificationValues(): void
    {
        $this->createAnalyzedDigestItem([
            'source_key' => 'hacker_news',
            'title' => 'Laravel release',
            'analysis_json' => $this->analysisJson('news_article', 0.95, 5),
            'analyzed_at' => CarbonImmutable::now()->subMinutes(1),
        ]);
        $this->createAnalyzedDigestItem([
            'source_key' => 'hacker_news',
            'title' => 'Laravel tutorial',
            'analysis_json' => $this->analysisJson('blog post', 0.7, 3),
            'analyzed_at' => CarbonImmutable::now()->subMinutes(2),
        ]);
        $this->createAnalyzedDigestItem([
            'source_key' => 'aws_news',
            'title' => 'AWS announcement',
            'analysis_json' => $this->analysisJson('news_article', 0.4, 4, 'Source content was incomplete.'),
            'analyzed_at' => CarbonImmutable::now()->subMinutes(3),
        ]);
        $this->createAnalyzedDigestItem([
            'source_key' => 'aws_news',
            'title' => 'Old analyzed item',
            'analysis_json' => $this->analysisJson('old_type', 0.1, 1),
            'analyzed_at' => CarbonImmutable::now()->subDays(40),
        ]);
        $this->createAnalyzedDigestItem([
            'source_key' => 'hacker_news',
            'title' => 'Malformed item',
            'analysis_json' => ['classification' => []],
            'analyzed_at' => CarbonImmutable::now()->subMinutes(4),
        ]);

        $report = app(AnalysisInsightsQuery::class)->report();

        self::assertSame([
            ['content_type' => 'news_article', 'count' => 2],
            ['content_type' => 'blog post', 'count' => 1],
        ], $report['content_types']);
        self::assertSame([
            ['source_key' => 'aws_news', 'content_type' => 'news_article', 'count' => 1],
            ['source_key' => 'hacker_news', 'content_type' => 'blog post', 'count' => 1],
            ['source_key' => 'hacker_news', 'content_type' => 'news_article', 'count' => 1],
        ], $report['content_types_by_source']);
        self::assertSame([
            ['label' => '0.0 - 0.2', 'count' => 0],
            ['label' => '0.3 - 0.5', 'count' => 1],
            ['label' => '0.6 - 0.8', 'count' => 1],
            ['label' => '0.9 - 1.0', 'count' => 1],
        ], $report['confidence_distribution']);
        self::assertSame([
            ['label' => '1', 'count' => 0],
            ['label' => '2', 'count' => 0],
            ['label' => '3', 'count' => 1],
            ['label' => '4', 'count' => 1],
            ['label' => '5', 'count' => 1],
        ], $report['importance_distribution']);
        self::assertCount(4, $report['recent_samples']);
        self::assertSame('Laravel release', $report['recent_samples'][0]['title']);
        self::assertSame([
            'source_key' => 'aws_news',
            'confidence' => 0.4,
            'content_type' => 'news_article',
            'title' => 'AWS announcement',
            'limitations' => 'Source content was incomplete.',
        ], array_intersect_key($report['low_confidence_items'][0], array_flip([
            'source_key',
            'confidence',
            'content_type',
            'title',
            'limitations',
        ])));
    }

    public function testRecentSamplesAndLowConfidenceItemsAreLimited(): void
    {
        for ($index = 1; $index <= 25; ++$index) {
            $this->createAnalyzedDigestItem([
                'title' => 'Analyzed ' . $index,
                'analysis_json' => $this->analysisJson('news_article', 0.2, 2),
                'analyzed_at' => CarbonImmutable::now()->subMinutes($index),
            ]);
        }

        $report = app(AnalysisInsightsQuery::class)->report(sampleLimit: 20, lowConfidenceLimit: 10);

        self::assertCount(20, $report['recent_samples']);
        self::assertCount(10, $report['low_confidence_items']);
        self::assertSame('Analyzed 1', $report['recent_samples'][0]['title']);
        self::assertSame('Analyzed 1', $report['low_confidence_items'][0]['title']);
    }

    public function testAuthorizedAdminCanAccessAnalysisInsightsPage(): void
    {
        config(['digestpipe.admin.allowed_emails' => ['admin@example.test']]);
        $user = User::factory()->create(['email' => 'admin@example.test']);

        $this->actingAs($user)
            ->get('/admin/analysis-insights')
            ->assertOk()
            ->assertSee('Analysis Insights')
            ->assertSee('Export Insights');
    }

    public function testAnalysisInsightsExporterBuildsMarkdownDownload(): void
    {
        $this->createAnalyzedDigestItem([
            'source_key' => 'hacker_news',
            'title' => 'Laravel release',
            'analysis_json' => $this->analysisJson('news_article', 0.95, 5),
        ]);

        $result = app(AnalysisInsightsExporter::class)->export(days: 30);

        self::assertSame('text/markdown; charset=UTF-8', $result->mimeType);
        self::assertStringStartsWith('digestpipe-analysis-insights-20260528-120000', $result->filename);
        self::assertStringContainsString('# digestpipe Analysis Insights Export', $result->content);
        self::assertStringContainsString('## Content Type Breakdown', $result->content);
        self::assertStringContainsString('news_article', $result->content);
        self::assertStringContainsString('Laravel release', $result->content);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createAnalyzedDigestItem(array $attributes = []): DigestItem
    {
        ++$this->digestItemSequence;
        $sequence = $this->digestItemSequence;

        return DigestItem::query()->create(array_merge([
            'source_key' => 'hacker_news',
            'source_name' => 'Hacker News',
            'external_id' => 'analysis-insights-' . $sequence,
            'identity_hash' => hash('sha256', 'analysis-insights-' . $sequence),
            'source_url' => 'https://example.test/articles/' . $sequence,
            'discussion_url' => null,
            'title' => 'Example title ' . $sequence,
            'excerpt' => 'Example excerpt ' . $sequence,
            'published_at' => CarbonImmutable::now()->subMinutes(40),
            'fetched_at' => CarbonImmutable::now()->subMinutes(35),
            'content_hash' => hash('sha256', 'analysis-insights-content-' . $sequence),
            'selection_status' => 'selected',
            'selection_score' => 10,
            'selection_reason' => 'above_analysis_threshold',
            'selection_result' => [
                'matched_good_keywords' => [],
                'matched_bad_keywords' => [],
            ],
            'selection_evaluated_at' => CarbonImmutable::now()->subMinutes(30),
            'article_content_status' => 'completed',
            'article_content_text' => 'Example content',
            'article_content_error' => null,
            'article_content_fetched_at' => CarbonImmutable::now()->subMinutes(25),
            'analysis_status' => 'completed',
            'analysis_json' => $this->analysisJson('news_article', 0.8, 3),
            'analysis_model' => 'fake',
            'analysis_error' => null,
            'analyzed_at' => CarbonImmutable::now()->subMinutes(20),
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function analysisJson(string $contentType, float $confidence, int $importance, ?string $limitations = null): array
    {
        return [
            'schema_version' => '1.0',
            'source_language' => 'en',
            'title' => [
                'original' => 'Example',
                'normalized' => 'Example',
            ],
            'content' => [
                'brief' => 'Brief',
                'detailed_summary' => 'Summary',
                'key_points' => ['Point'],
                'background' => null,
                'why_it_matters' => 'Reason',
                'limitations' => $limitations,
            ],
            'classification' => [
                'content_type' => $contentType,
                'topics' => ['general'],
                'entities' => [],
                'importance' => $importance,
                'confidence' => $confidence,
            ],
        ];
    }
}
