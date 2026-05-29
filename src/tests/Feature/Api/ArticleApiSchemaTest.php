<?php

namespace Tests\Feature\Api;

use App\Models\DigestItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\OpenApiSchemaValidator;
use Tests\TestCase;

/**
 * @internal
 */
class ArticleApiSchemaTest extends TestCase
{
    use RefreshDatabase;

    private OpenApiSchemaValidator $openApi;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-29T12:00:00Z'));
        $this->openApi = OpenApiSchemaValidator::fromFile(base_path('../docs/openapi.yaml'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function testIndexResponseMatchesOpenApiSchema(): void
    {
        $this->authenticate();
        $this->createDigestItem();

        $response = $this->getJson('/api/articles')
            ->assertOk();

        $this->openApi->validateComponent('ArticleIndexResponse', $response->json());
        $this->assertInternalFieldsAreNotExposed($response->json());
    }

    public function testDetailResponseMatchesOpenApiSchema(): void
    {
        $this->authenticate();
        $item = $this->createDigestItem();

        $response = $this->getJson('/api/articles/' . $item->id)
            ->assertOk();

        $this->openApi->validateComponent('ArticleDetailResponse', $response->json());
        $this->assertInternalFieldsAreNotExposed($response->json());
    }

    public function testOpenApiDocumentDefinesOnlyImplementedArticleApiParameters(): void
    {
        $document = $this->openApi->document();

        $paths = $document['paths'] ?? null;
        self::assertIsArray($paths);
        self::assertArrayHasKey('/api/articles', $paths);
        self::assertArrayHasKey('/api/articles/{id}', $paths);
        self::assertSame('3.1.0', $document['openapi']);

        $articlesPath = $paths['/api/articles'];
        self::assertIsArray($articlesPath);

        $getOperation = $articlesPath['get'] ?? null;
        self::assertIsArray($getOperation);

        $parameters = $getOperation['parameters'] ?? null;
        self::assertIsArray($parameters);

        $parameterNames = array_map(
            static function (mixed $parameter): string {
                self::assertIsArray($parameter);
                self::assertArrayHasKey('name', $parameter);
                self::assertIsString($parameter['name']);

                return $parameter['name'];
            },
            $parameters,
        );

        self::assertSame(['from', 'to', 'source', 'limit'], $parameterNames);
    }

    /**
     * @param mixed $json
     */
    private function assertInternalFieldsAreNotExposed(mixed $json): void
    {
        $body = json_encode($json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        foreach ($this->forbiddenExposureTokens() as $token) {
            self::assertStringNotContainsString($token, $body);
        }
    }

    /**
     * @return list<string>
     */
    private function forbiddenExposureTokens(): array
    {
        return [
            'article_content_text',
            'Raw article content must not be exposed.',
            'selection_result',
            'matched_good_keywords',
            'matched_bad_keywords',
            'raw_model_response',
            'prompt',
            'api_key',
            'access_token',
            'refresh_token',
            'laravel_cloud_api_token',
            'manual_rating',
            'manual_rated_at',
        ];
    }

    private function authenticate(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['digests:read']);
    }

    private function createDigestItem(): DigestItem
    {
        return DigestItem::query()->create([
            'source_key' => 'laravel_news',
            'source_name' => 'Laravel News',
            'external_id' => 'schema-test-article',
            'identity_hash' => hash('sha256', 'schema-test-article'),
            'source_url' => 'https://laravel-news.example.test/moat',
            'discussion_url' => null,
            'title' => 'Moat: A Security Review for Your GitHub Account',
            'excerpt' => 'Example excerpt.',
            'published_at' => CarbonImmutable::parse('2026-05-29T07:00:00Z'),
            'fetched_at' => CarbonImmutable::parse('2026-05-29T07:05:00Z'),
            'content_hash' => hash('sha256', 'schema-test-content'),
            'selection_status' => 'selected',
            'selection_score' => 29,
            'selection_reason' => 'above_analysis_threshold',
            'selection_result' => [
                'score' => 29,
                'status' => 'selected',
                'matched_good_keywords' => ['GitHub'],
                'matched_bad_keywords' => [],
                'reason' => 'above_analysis_threshold',
            ],
            'article_content_status' => 'completed',
            'article_content_text' => 'Raw article content must not be exposed.',
            'article_content_error' => null,
            'analysis_status' => 'completed',
            'analysis_json' => $this->analysisJson(),
            'analysis_model' => 'gpt-4o-mini',
            'analysis_error' => null,
            'analyzed_at' => CarbonImmutable::parse('2026-05-29T07:10:00Z'),
            'manual_rating' => 5,
            'manual_rated_at' => CarbonImmutable::parse('2026-05-29T08:00:00Z'),
        ]);
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
                'original' => 'Moat: A Security Review for Your GitHub Account',
                'normalized' => 'Moat: A Security Review for Your GitHub Account',
            ],
            'content' => [
                'brief' => 'A concise summary.',
                'detailed_summary' => 'A detailed summary.',
                'key_points' => [
                    'A concrete key point.',
                ],
                'background' => null,
                'why_it_matters' => 'Why this matters.',
                'limitations' => null,
            ],
            'classification' => [
                'content_type' => 'news_article',
                'topics' => [
                    'security',
                ],
                'entities' => [
                    'GitHub',
                ],
                'importance' => 4,
                'confidence' => 0.91,
            ],
        ];
    }
}
