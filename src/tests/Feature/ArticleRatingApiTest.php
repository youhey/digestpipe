<?php

namespace Tests\Feature;

use App\Models\DigestItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * @internal
 */
class ArticleRatingApiTest extends TestCase
{
    use RefreshDatabase;

    private int $digestItemSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-31T10:15:00Z'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function testTokenWithRateAbilityCanSetAllowedRatings(): void
    {
        foreach ([-1, 1, 5] as $rating) {
            $this->authenticate();
            $item = $this->createDigestItem();

            $response = $this->putJson("/api/articles/{$item->id}/rating", [
                'rating' => $rating,
            ])->assertOk();

            $response
                ->assertJsonPath('article_rating.article_id', $item->id)
                ->assertJsonPath('article_rating.rating', $rating)
                ->assertJsonPath('article_rating.rated_at', '2026-05-31T10:15:00.000000Z')
                ->assertJsonMissingPath('article_rating.manual_rating')
                ->assertJsonMissingPath('article_rating.manual_rated_at');

            self::assertStringNotContainsString('manual_rating', $this->jsonResponseBody($response->json()));
            self::assertStringNotContainsString('manual_rated_at', $this->jsonResponseBody($response->json()));

            $item->refresh();
            self::assertSame($rating, $item->manual_rating);
            self::assertSame('2026-05-31T10:15:00.000000Z', $item->manual_rated_at?->toJSON());
        }
    }

    public function testPutOverwritesExistingRating(): void
    {
        $this->authenticate();
        $item = $this->createDigestItem([
            'manual_rating' => 1,
            'manual_rated_at' => CarbonImmutable::parse('2026-05-30T10:15:00Z'),
        ]);

        $this->putJson("/api/articles/{$item->id}/rating", [
            'rating' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('article_rating.rating', 5)
            ->assertJsonPath('article_rating.rated_at', '2026-05-31T10:15:00.000000Z');

        $item->refresh();
        self::assertSame(5, $item->manual_rating);
        self::assertSame('2026-05-31T10:15:00.000000Z', $item->manual_rated_at?->toJSON());
    }

    public function testPutRejectsInvalidRatings(): void
    {
        $this->authenticate();
        $item = $this->createDigestItem();

        foreach ([0, -2, 6, null, '5', 'bad'] as $rating) {
            $this->putJson("/api/articles/{$item->id}/rating", [
                'rating' => $rating,
            ])->assertUnprocessable();
        }

        $this->putJson("/api/articles/{$item->id}/rating", [])
            ->assertUnprocessable();
    }

    public function testPutRequiresAuthenticationAndRateAbility(): void
    {
        $item = $this->createDigestItem();

        $this->putJson("/api/articles/{$item->id}/rating", [
            'rating' => 5,
        ])->assertUnauthorized();

        $this->authenticate(['digests:read']);

        $this->putJson("/api/articles/{$item->id}/rating", [
            'rating' => 5,
        ])->assertForbidden();
    }

    public function testPutReturnsNotFoundForNonVisibleArticle(): void
    {
        $this->authenticate();
        $pendingItem = $this->createDigestItem([
            'analysis_status' => 'pending',
            'analysis_json' => $this->analysisJson(),
        ]);
        $missingJsonItem = $this->createDigestItem([
            'analysis_status' => 'completed',
            'analysis_json' => null,
        ]);

        $this->putJson('/api/articles/999999/rating', ['rating' => 5])
            ->assertNotFound();
        $this->putJson("/api/articles/{$pendingItem->id}/rating", ['rating' => 5])
            ->assertNotFound();
        $this->putJson("/api/articles/{$missingJsonItem->id}/rating", ['rating' => 5])
            ->assertNotFound();
    }

    public function testTokenWithRateAbilityCanClearRating(): void
    {
        $this->authenticate();
        $item = $this->createDigestItem([
            'manual_rating' => 5,
            'manual_rated_at' => CarbonImmutable::parse('2026-05-31T09:15:00Z'),
        ]);

        $response = $this->deleteJson("/api/articles/{$item->id}/rating")
            ->assertOk()
            ->assertJsonPath('article_rating.article_id', $item->id)
            ->assertJsonPath('article_rating.rating', null)
            ->assertJsonPath('article_rating.rated_at', null);

        self::assertStringNotContainsString('manual_rating', $this->jsonResponseBody($response->json()));
        self::assertStringNotContainsString('manual_rated_at', $this->jsonResponseBody($response->json()));

        $item->refresh();
        self::assertNull($item->manual_rating);
        self::assertNull($item->manual_rated_at);
    }

    public function testDeleteRequiresAuthenticationAndRateAbility(): void
    {
        $item = $this->createDigestItem([
            'manual_rating' => 5,
            'manual_rated_at' => CarbonImmutable::parse('2026-05-31T09:15:00Z'),
        ]);

        $this->deleteJson("/api/articles/{$item->id}/rating")
            ->assertUnauthorized();

        $this->authenticate(['digests:read']);

        $this->deleteJson("/api/articles/{$item->id}/rating")
            ->assertForbidden();
    }

    public function testDeleteReturnsNotFoundForNonVisibleArticle(): void
    {
        $this->authenticate();
        $pendingItem = $this->createDigestItem([
            'analysis_status' => 'pending',
            'analysis_json' => $this->analysisJson(),
        ]);
        $missingJsonItem = $this->createDigestItem([
            'analysis_status' => 'completed',
            'analysis_json' => null,
        ]);

        $this->deleteJson('/api/articles/999999/rating')
            ->assertNotFound();
        $this->deleteJson("/api/articles/{$pendingItem->id}/rating")
            ->assertNotFound();
        $this->deleteJson("/api/articles/{$missingJsonItem->id}/rating")
            ->assertNotFound();
    }

    /**
     * @param list<string> $abilities
     */
    private function authenticate(array $abilities = ['digests:rate']): void
    {
        Sanctum::actingAs(User::factory()->create(), $abilities);
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
            'external_id' => 'rating-api-article-' . $sequence,
            'identity_hash' => hash('sha256', 'rating-api-article-' . $sequence),
            'source_url' => 'https://news.example.test/' . $sequence,
            'discussion_url' => null,
            'title' => 'Example title ' . $sequence,
            'excerpt' => 'Example excerpt ' . $sequence,
            'published_at' => CarbonImmutable::parse('2026-05-31T09:00:00Z'),
            'fetched_at' => CarbonImmutable::parse('2026-05-31T09:05:00Z'),
            'content_hash' => hash('sha256', 'rating-api-content-' . $sequence),
            'selection_status' => 'selected',
            'selection_score' => 12,
            'selection_reason' => 'above_analysis_threshold',
            'selection_result' => [
                'score' => 12,
                'status' => 'selected',
                'matched_good_keywords' => ['API'],
                'matched_bad_keywords' => [],
                'reason' => 'above_analysis_threshold',
            ],
            'article_content_status' => 'completed',
            'article_content_text' => 'Raw article content must not be exposed.',
            'article_content_error' => null,
            'analysis_status' => 'completed',
            'analysis_json' => $this->analysisJson(),
            'analysis_model' => 'gpt-test',
            'analysis_error' => null,
            'analyzed_at' => CarbonImmutable::parse('2026-05-31T09:10:00Z'),
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function analysisJson(): array
    {
        return [
            'schema_version' => '1.0',
            'source_language' => 'en',
            'title' => [
                'original' => 'Example title',
                'normalized' => 'Example title',
            ],
            'content' => [
                'brief' => 'Example brief.',
                'detailed_summary' => 'Example detailed summary.',
                'key_points' => ['Example key point.'],
                'background' => null,
                'why_it_matters' => 'It may matter to downstream consumers.',
                'limitations' => null,
            ],
            'classification' => [
                'content_type' => 'news_article',
                'topics' => ['technology'],
                'entities' => ['Example Entity'],
                'importance' => 3,
                'confidence' => 0.8,
            ],
        ];
    }

    private function jsonResponseBody(mixed $json): string
    {
        return json_encode($json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
