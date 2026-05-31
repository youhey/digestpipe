<?php

namespace Tests\Feature;

use App\Digests\DigestExportItemBuilder;
use App\Models\DigestItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * @internal
 */
class ArticleApiTest extends TestCase
{
    use RefreshDatabase;

    private int $digestItemSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-24T12:00:00Z'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function testIndexRequiresAuthentication(): void
    {
        $this->getJson('/api/articles')
            ->assertUnauthorized();
    }

    public function testShowRequiresAuthentication(): void
    {
        $item = $this->createDigestItem();

        $this->getJson('/api/articles/' . $item->id)
            ->assertUnauthorized();
    }

    public function testTokenWithoutDigestReadAbilityIsRejected(): void
    {
        $this->authenticate([]);

        $this->getJson('/api/articles')
            ->assertForbidden();
    }

    public function testValidTokenCanAccessIndex(): void
    {
        $this->authenticate();
        $item = $this->createDigestItem([
            'selection_status' => 'selected',
            'selection_score' => 12,
            'selection_result' => [
                'matched_good_keywords' => ['AWS'],
                'matched_bad_keywords' => [],
            ],
        ]);

        $this->getJson('/api/articles')
            ->assertOk()
            ->assertJsonPath('articles.0.id', $item->id)
            ->assertJsonPath('articles.0.selection.status', 'selected')
            ->assertJsonPath('articles.0.selection.score', 12)
            ->assertJsonMissingPath('articles.0.selection.matched_good_keywords')
            ->assertJsonMissingPath('articles.0.selection.matched_bad_keywords')
            ->assertJsonPath('meta.limit', 100);
    }

    public function testValidTokenCanAccessShow(): void
    {
        $this->authenticate();
        $item = $this->createDigestItem();

        $this->getJson('/api/articles/' . $item->id)
            ->assertOk()
            ->assertJsonPath('article.id', $item->id);
    }

    public function testIndexReturnsOnlyCompletedAnalysisItems(): void
    {
        $this->authenticate();
        $readyItem = $this->createDigestItem();

        foreach (['pending', 'queued', 'processing', 'failed', 'skipped'] as $status) {
            $this->createDigestItem([
                'analysis_status' => $status,
                'analysis_json' => $this->analysisJson('Excluded ' . $status),
            ]);
        }

        $this->createDigestItem([
            'analysis_status' => 'completed',
            'analysis_json' => null,
        ]);

        $this->getJson('/api/articles')
            ->assertOk()
            ->assertJsonCount(1, 'articles')
            ->assertJsonPath('articles.0.id', $readyItem->id);
    }

    public function testDefaultWindowReturnsRecentItemsAndExcludesOldItems(): void
    {
        $this->authenticate();
        $recentItem = $this->createDigestItem([
            'published_at' => CarbonImmutable::parse('2026-05-24T11:00:00Z'),
        ]);
        $this->createDigestItem([
            'published_at' => CarbonImmutable::parse('2026-05-23T11:59:59Z'),
        ]);

        $response = $this->getJson('/api/articles')
            ->assertOk()
            ->assertJsonCount(1, 'articles')
            ->assertJsonPath('articles.0.id', $recentItem->id);

        $response->assertJsonPath('meta.from', '2026-05-23T12:00:00.000000Z');
        $response->assertJsonPath('meta.to', '2026-05-24T12:00:00.000000Z');
    }

    public function testFromToSourceAndLimitFiltersWork(): void
    {
        $this->authenticate();
        $matchingItem = $this->createDigestItem([
            'source_key' => 'hacker_news',
            'source_name' => 'Hacker News',
            'published_at' => CarbonImmutable::parse('2026-05-24T10:00:00Z'),
        ]);
        $this->createDigestItem([
            'source_key' => 'reuters_top',
            'source_name' => 'Reuters Top News',
            'published_at' => CarbonImmutable::parse('2026-05-24T10:00:00Z'),
        ]);
        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'source_name' => 'Hacker News',
            'published_at' => CarbonImmutable::parse('2026-05-24T07:59:59Z'),
        ]);
        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'source_name' => 'Hacker News',
            'published_at' => CarbonImmutable::parse('2026-05-24T11:00:00Z'),
        ]);

        $this->getJson('/api/articles?source=hacker_news&from=2026-05-24T08:00:00Z&to=2026-05-24T10:30:00Z&limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'articles')
            ->assertJsonPath('articles.0.id', $matchingItem->id)
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('meta.limit', 1);
    }

    public function testIndexUsesFetchedAtWhenPublishedAtIsMissing(): void
    {
        $this->authenticate();
        $item = $this->createDigestItem([
            'published_at' => null,
            'fetched_at' => CarbonImmutable::parse('2026-05-24T10:00:00Z'),
        ]);

        $this->getJson('/api/articles?from=2026-05-24T09:00:00Z&to=2026-05-24T11:00:00Z')
            ->assertOk()
            ->assertJsonCount(1, 'articles')
            ->assertJsonPath('articles.0.id', $item->id);
    }

    public function testLimitRejectsValuesOverMaximum(): void
    {
        $this->authenticate();

        $this->getJson('/api/articles?limit=501')
            ->assertUnprocessable();
    }

    public function testInvalidDateParametersReturnValidationError(): void
    {
        $this->authenticate();

        $this->getJson('/api/articles?from=not-a-date')
            ->assertUnprocessable();
    }

    public function testFromAfterToReturnsValidationError(): void
    {
        $this->authenticate();

        $this->getJson('/api/articles?from=2026-05-24T12:00:00Z&to=2026-05-24T11:00:00Z')
            ->assertUnprocessable();
    }

    public function testIndexResponseShapeMatchesDigestExportItemBuilder(): void
    {
        $this->authenticate();
        $item = $this->createDigestItem([
            'source_key' => 'hacker_news',
            'source_name' => 'Hacker News',
            'source_url' => 'https://example.test/article',
            'discussion_url' => 'https://news.ycombinator.com/item?id=123',
            'article_content_text' => 'Raw article content must not be exposed.',
            'analysis_json' => $this->analysisJson('Shape brief', ['technology']),
            'analysis_model' => 'gpt-test',
            'analyzed_at' => CarbonImmutable::parse('2026-05-24T11:30:00Z'),
        ]);

        $expected = $this->app->make(DigestExportItemBuilder::class)->build($item);

        $response = $this->getJson('/api/articles')
            ->assertOk()
            ->assertJsonPath('articles.0.id', $item->id);

        self::assertEquals($expected, $response->json('articles.0'));
        self::assertStringNotContainsString('Raw article content must not be exposed.', $this->jsonResponseBody($response->json()));
    }

    public function testShowReturnsOneCompletedAnalysisItemById(): void
    {
        $this->authenticate();
        $item = $this->createDigestItem([
            'analysis_json' => $this->analysisJson('Show brief', ['technology']),
        ]);

        $response = $this->getJson('/api/articles/' . $item->id)
            ->assertOk()
            ->assertJsonPath('article.id', $item->id);

        self::assertEquals(
            $this->app->make(DigestExportItemBuilder::class)->build($item),
            $response->json('article'),
        );
    }

    public function testShowReturnsNotFoundForMissingOrIncompleteItems(): void
    {
        $this->authenticate();
        $pendingItem = $this->createDigestItem([
            'analysis_status' => 'pending',
            'analysis_json' => $this->analysisJson('Pending brief'),
        ]);
        $missingJsonItem = $this->createDigestItem([
            'analysis_status' => 'completed',
            'analysis_json' => null,
        ]);

        $this->getJson('/api/articles/999999')
            ->assertNotFound();
        $this->getJson('/api/articles/' . $pendingItem->id)
            ->assertNotFound();
        $this->getJson('/api/articles/' . $missingJsonItem->id)
            ->assertNotFound();
    }

    public function testShowDoesNotExposeRawArticleContent(): void
    {
        $this->authenticate();
        $item = $this->createDigestItem([
            'article_content_text' => 'Raw article content must not be exposed.',
        ]);

        $response = $this->getJson('/api/articles/' . $item->id)
            ->assertOk();

        self::assertStringNotContainsString('Raw article content must not be exposed.', $this->jsonResponseBody($response->json()));
    }

    /**
     * @param list<string> $abilities
     */
    private function authenticate(array $abilities = ['digests:read']): void
    {
        Sanctum::actingAs(User::factory()->create(), $abilities);
    }

    private function jsonResponseBody(mixed $json): string
    {
        return json_encode($json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createDigestItem(array $attributes = []): DigestItem
    {
        ++$this->digestItemSequence;
        $sequence = $this->digestItemSequence;

        return DigestItem::query()->create(array_merge([
            'source_key' => 'example',
            'source_name' => 'Example Source',
            'external_id' => 'api-article-' . $sequence,
            'identity_hash' => hash('sha256', 'api-article-' . $sequence),
            'source_url' => 'https://news.example.test/' . $sequence,
            'discussion_url' => null,
            'title' => 'Example title ' . $sequence,
            'excerpt' => 'Example excerpt ' . $sequence,
            'published_at' => CarbonImmutable::parse('2026-05-24T11:00:00Z'),
            'fetched_at' => CarbonImmutable::parse('2026-05-24T11:05:00Z'),
            'content_hash' => hash('sha256', 'api-article-content-' . $sequence),
            'selection_status' => 'selected',
            'selection_score' => 12,
            'selection_reason' => 'above_analysis_threshold',
            'selection_result' => [
                'score' => 12,
                'status' => 'selected',
                'matched_good_keywords' => ['AWS'],
                'matched_bad_keywords' => [],
                'reason' => 'above_analysis_threshold',
            ],
            'article_content_status' => 'completed',
            'article_content_text' => null,
            'article_content_error' => null,
            'analysis_status' => 'completed',
            'analysis_json' => $this->analysisJson('Example brief ' . $sequence),
            'analysis_model' => 'gpt-test',
            'analysis_error' => null,
            'analyzed_at' => CarbonImmutable::parse('2026-05-24T11:10:00Z'),
        ], $attributes));
    }

    /**
     * @param list<string> $topics
     *
     * @return array<string, mixed>
     */
    private function analysisJson(string $brief, array $topics = ['general']): array
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
                'detailed_summary' => $brief . ' Detailed context.',
                'key_points' => [$brief],
                'background' => null,
                'why_it_matters' => 'It may matter to downstream consumers.',
                'limitations' => null,
            ],
            'classification' => [
                'content_type' => 'news_article',
                'topics' => $topics,
                'entities' => ['Example Entity'],
                'importance' => 3,
                'confidence' => 0.8,
            ],
        ];
    }
}
