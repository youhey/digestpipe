<?php

namespace Tests\Feature;

use App\Admin\SourceDetailQuery;
use App\Models\DigestItem;
use App\Models\FeedSource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @internal
 */
class SourceDetailQueryTest extends TestCase
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

    public function testSourceWithNoItemsReturnsEmptySourceDetail(): void
    {
        $source = $this->createFeedSource('hacker_news');

        $report = app(SourceDetailQuery::class)->report($source);

        self::assertSame('hacker_news', $report['source']['key']);
        self::assertSame(0, $report['kpis']['total']);
        self::assertSame(0, $report['kpis']['selected']);
        self::assertSame(0.0, $report['kpis']['selected_rate']);
        self::assertSame(0, $report['kpis']['analysis_completed']);
        self::assertSame(0.0, $report['kpis']['analysis_completed_rate']);
        self::assertSame([
            ['status' => 'selected', 'count' => 0],
            ['status' => 'skipped', 'count' => 0],
            ['status' => 'pending', 'count' => 0],
            ['status' => 'other', 'count' => 0],
        ], $report['selection_statuses']);
        self::assertSame([], $report['keywords']['positive']);
        self::assertSame([], $report['keywords']['negative']);
        self::assertSame([], $report['content_types']);
        self::assertSame([], $report['recent']['selected']);
        self::assertSame([], $report['recent']['skipped']);
        self::assertSame([], $report['recent']['failed']);
    }

    public function testSourceDetailIsScopedToSourceAndAggregatesSignals(): void
    {
        $source = $this->createFeedSource('hacker_news');
        $this->createFeedSource('aws_news');

        $selected = $this->createDigestItem([
            'source_key' => 'hacker_news',
            'title' => 'Laravel source item',
            'selection_status' => 'selected',
            'selection_score' => 20,
            'selection_result' => [
                'matched_good_keywords' => ['Laravel', 'PHP'],
                'matched_bad_keywords' => [],
            ],
            'article_content_status' => 'completed',
            'analysis_status' => 'completed',
            'analysis_json' => $this->analysisJson('news_article'),
            'analyzed_at' => CarbonImmutable::now()->subMinutes(1),
        ]);
        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'title' => 'Crypto skipped item',
            'selection_status' => 'skipped',
            'selection_score' => -100,
            'selection_result' => [
                'matched_good_keywords' => ['PHP'],
                'matched_bad_keywords' => ['crypto'],
            ],
            'article_content_status' => 'pending',
            'analysis_status' => 'pending',
            'analysis_json' => null,
            'selection_evaluated_at' => CarbonImmutable::now()->subMinutes(2),
        ]);
        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'title' => 'Pending item',
            'selection_status' => 'needs_content',
            'selection_score' => 1,
            'article_content_status' => 'queued',
            'analysis_status' => 'pending',
        ]);
        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'title' => 'Failed item',
            'selection_status' => 'selected',
            'selection_score' => 12,
            'article_content_status' => 'completed',
            'analysis_status' => 'failed',
            'analysis_json' => $this->analysisJson('tutorial'),
            'analysis_error' => 'Example analysis failure',
            'updated_at' => CarbonImmutable::now()->subMinutes(3),
        ]);
        $this->createDigestItem([
            'source_key' => 'aws_news',
            'title' => 'Other source item',
            'selection_status' => 'selected',
            'selection_score' => 99,
            'selection_result' => [
                'matched_good_keywords' => ['AWS'],
                'matched_bad_keywords' => [],
            ],
            'analysis_status' => 'completed',
            'analysis_json' => $this->analysisJson('announcement'),
        ]);

        $report = app(SourceDetailQuery::class)->report($source);

        self::assertSame(4, $report['kpis']['total']);
        self::assertSame(2, $report['kpis']['selected']);
        self::assertSame(50.0, $report['kpis']['selected_rate']);
        self::assertSame(1, $report['kpis']['skipped']);
        self::assertSame(25.0, $report['kpis']['skipped_rate']);
        self::assertSame(1, $report['kpis']['pending']);
        self::assertSame(25.0, $report['kpis']['pending_rate']);
        self::assertSame(0, $report['kpis']['content_failed']);
        self::assertSame(0.0, $report['kpis']['content_failed_rate']);
        self::assertSame(1, $report['kpis']['analysis_failed']);
        self::assertSame(25.0, $report['kpis']['analysis_failed_rate']);
        self::assertSame(1, $report['kpis']['analysis_completed']);
        self::assertSame(25.0, $report['kpis']['analysis_completed_rate']);
        self::assertSame(-16.75, $report['kpis']['average_score']);
        self::assertSame([
            ['status' => 'selected', 'count' => 2],
            ['status' => 'skipped', 'count' => 1],
            ['status' => 'pending', 'count' => 1],
            ['status' => 'other', 'count' => 0],
        ], $report['selection_statuses']);
        self::assertSame([
            ['keyword' => 'PHP', 'count' => 2],
            ['keyword' => 'Laravel', 'count' => 1],
        ], $report['keywords']['positive']);
        self::assertSame([
            ['keyword' => 'crypto', 'count' => 1],
        ], $report['keywords']['negative']);
        self::assertSame([
            ['content_type' => 'news_article', 'count' => 1],
        ], $report['content_types']);
        self::assertSame($selected->id, $report['recent']['selected'][0]['id']);
        self::assertSame('Crypto skipped item', $report['recent']['skipped'][0]['title']);
        self::assertSame('Failed item', $report['recent']['failed'][0]['title']);
    }

    public function testRecentSourceItemsAreLimited(): void
    {
        $source = $this->createFeedSource('hacker_news');

        for ($index = 1; $index <= 8; ++$index) {
            $this->createDigestItem([
                'source_key' => 'hacker_news',
                'title' => 'Selected ' . $index,
                'selection_status' => 'selected',
                'selection_evaluated_at' => CarbonImmutable::now()->subMinutes($index),
            ]);
        }

        $report = app(SourceDetailQuery::class)->report($source, recentLimit: 5);

        self::assertCount(5, $report['recent']['selected']);
        self::assertSame('Selected 1', $report['recent']['selected'][0]['title']);
    }

    public function testUnknownSourceFailsSafely(): void
    {
        $this->expectException(ModelNotFoundException::class);

        app(SourceDetailQuery::class)->reportForSourceKey('missing_source');
    }

    public function testAuthorizedAdminCanAccessFeedSourceDetailPage(): void
    {
        config(['digestpipe.admin.allowed_emails' => ['admin@example.test']]);
        $user = User::factory()->create(['email' => 'admin@example.test']);
        $source = $this->createFeedSource('hacker_news');

        $this->actingAs($user)
            ->get('/admin/feed-sources/' . $source->id)
            ->assertOk()
            ->assertSee('Source detail: hacker_news');
    }

    private function createFeedSource(string $key): FeedSource
    {
        return FeedSource::query()->create([
            'key' => $key,
            'name' => str_replace('_', ' ', $key),
            'url' => 'https://example.test/' . $key . '.xml',
            'language' => $key === 'aws_news' ? 'en' : 'en',
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
            'external_id' => 'source-detail-' . $sequence,
            'identity_hash' => hash('sha256', 'source-detail-' . $sequence),
            'source_url' => 'https://example.test/articles/' . $sequence,
            'discussion_url' => null,
            'title' => 'Example title ' . $sequence,
            'excerpt' => 'Example excerpt ' . $sequence,
            'published_at' => CarbonImmutable::now()->subMinutes(40),
            'fetched_at' => CarbonImmutable::now()->subMinutes(35),
            'content_hash' => hash('sha256', 'source-detail-content-' . $sequence),
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

    /**
     * @return array<string, mixed>
     */
    private function analysisJson(string $contentType): array
    {
        return [
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
