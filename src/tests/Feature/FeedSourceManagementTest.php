<?php

namespace Tests\Feature;

use App\Feeds\FeedSourceRepository;
use App\Models\FeedSource;
use Database\Seeders\FeedSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * @internal
 */
class FeedSourceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function testFeedSourcesTableHasExpectedColumns(): void
    {
        self::assertTrue(Schema::hasTable('feed_sources'));

        foreach (['id', 'key', 'name', 'url', 'language', 'enabled', 'analysis_enabled', 'tier', 'category', 'sort_order', 'created_at', 'updated_at'] as $column) {
            self::assertTrue(Schema::hasColumn('feed_sources', $column), "Missing column: {$column}");
        }
    }

    public function testSeederInsertsDefaultFeedSources(): void
    {
        $this->seed(FeedSourceSeeder::class);

        $this->assertDatabaseCount('feed_sources', 18);
        $this->assertDatabaseHas('feed_sources', [
            'key' => 'hacker_news',
            'enabled' => true,
            'analysis_enabled' => true,
            'tier' => 'core',
            'category' => 'aggregator',
            'sort_order' => 10,
        ]);
        $this->assertDatabaseHas('feed_sources', [
            'key' => 'noted_lol',
            'enabled' => false,
            'analysis_enabled' => false,
            'tier' => 'candidate',
            'category' => 'self-hosted',
            'sort_order' => 180,
        ]);
    }

    public function testSeederDoesNotOverwriteExistingEditedFeedSource(): void
    {
        FeedSource::query()->create([
            'key' => 'hacker_news',
            'name' => 'Edited Hacker News',
            'url' => 'https://feeds.example.test/edited.xml',
            'language' => 'en',
            'enabled' => false,
            'analysis_enabled' => false,
            'tier' => 'candidate',
            'category' => 'aggregator',
            'sort_order' => 999,
        ]);

        $this->seed(FeedSourceSeeder::class);

        $this->assertDatabaseHas('feed_sources', [
            'key' => 'hacker_news',
            'name' => 'Edited Hacker News',
            'url' => 'https://feeds.example.test/edited.xml',
            'enabled' => false,
            'analysis_enabled' => false,
            'sort_order' => 999,
        ]);
    }

    public function testRepositoryReturnsEnabledFeedSourcesInSortOrder(): void
    {
        $this->createFeedSource('second_source', enabled: true, analysisEnabled: true, sortOrder: 20);
        $this->createFeedSource('first_source', enabled: true, analysisEnabled: true, sortOrder: 10);
        $this->createFeedSource('disabled_source', enabled: false, analysisEnabled: false, sortOrder: 5);

        $sources = app(FeedSourceRepository::class)->enabledSources();

        self::assertSame(['first_source', 'second_source'], array_map(
            static fn ($source): string => $source->key,
            $sources,
        ));
    }

    public function testRepositoryReturnsOnlyAnalysisEnabledSources(): void
    {
        $this->createFeedSource('analysis_source', enabled: true, analysisEnabled: true, sortOrder: 10);
        $this->createFeedSource('fetch_only_source', enabled: true, analysisEnabled: false, sortOrder: 20);
        $this->createFeedSource('disabled_source', enabled: false, analysisEnabled: false, sortOrder: 30);

        $sources = app(FeedSourceRepository::class)->analysisEnabledSources();

        self::assertSame(['analysis_source'], array_map(
            static fn ($source): string => $source->key,
            $sources,
        ));
    }

    public function testOrderingFallsBackToIdWhenSortOrderMatches(): void
    {
        $first = $this->createFeedSource('first_source', sortOrder: 10);
        $second = $this->createFeedSource('second_source', sortOrder: 10);

        $sources = app(FeedSourceRepository::class)->allSources();

        self::assertSame([$first->key, $second->key], array_map(
            static fn ($source): string => $source->key,
            $sources,
        ));
    }

    public function testValidationRejectsInvalidKeyFormat(): void
    {
        $this->expectException(ValidationException::class);

        $this->createFeedSource('Invalid-Key');
    }

    public function testValidationRejectsInvalidUrl(): void
    {
        $this->expectException(ValidationException::class);

        $this->createFeedSource('invalid_url_source', url: 'ftp://example.test/feed.xml');
    }

    public function testValidationRejectsUnsupportedLanguage(): void
    {
        $this->expectException(ValidationException::class);

        $this->createFeedSource('invalid_language_source', language: 'fr');
    }

    public function testValidationRejectsUnsupportedTier(): void
    {
        $this->expectException(ValidationException::class);

        $this->createFeedSource('invalid_tier_source', tier: 'experimental');
    }

    public function testValidationRejectsAnalysisEnabledWhenDisabled(): void
    {
        $this->expectException(ValidationException::class);

        $this->createFeedSource('inconsistent_source', enabled: false, analysisEnabled: true);
    }

    private function createFeedSource(
        string $key,
        string $url = 'https://feeds.example.test/feed.xml',
        string $language = 'en',
        bool $enabled = true,
        bool $analysisEnabled = true,
        string $tier = 'core',
        string $category = 'programming',
        int $sortOrder = 100,
    ): FeedSource {
        return FeedSource::query()->create([
            'key' => $key,
            'name' => $key,
            'url' => str_replace('feed.xml', $key . '.xml', $url),
            'language' => $language,
            'enabled' => $enabled,
            'analysis_enabled' => $analysisEnabled,
            'tier' => $tier,
            'category' => $category,
            'sort_order' => $sortOrder,
        ]);
    }
}
