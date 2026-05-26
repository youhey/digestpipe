<?php

namespace Tests\Feature;

use App\Models\DigestItem;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * @internal
 */
class SelectionRollbackCommandTest extends TestCase
{
    use RefreshDatabase;

    private int $digestItemSequence = 0;

    public function testSourceOptionIsRequired(): void
    {
        $exitCode = Artisan::call('digestpipe:selection:rollback', [
            '--status' => 'skipped',
        ]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('The --source option is required.', Artisan::output());
    }

    public function testStatusOptionIsRequired(): void
    {
        $exitCode = Artisan::call('digestpipe:selection:rollback', [
            '--source' => 'hacker_news',
        ]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('The --status option is required.', Artisan::output());
    }

    public function testUnsupportedStatusFailsClearly(): void
    {
        $exitCode = Artisan::call('digestpipe:selection:rollback', [
            '--source' => 'hacker_news',
            '--status' => 'selected',
        ]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Unsupported rollback status: selected', Artisan::output());
    }

    public function testDryRunDoesNotUpdateRecords(): void
    {
        $item = $this->createDigestItem();

        $exitCode = Artisan::call('digestpipe:selection:rollback', [
            '--source' => 'hacker_news',
            '--status' => 'skipped',
            '--dry-run' => true,
        ]);

        self::assertSame(0, $exitCode);
        $output = Artisan::output();
        self::assertStringContainsString('source: hacker_news', $output);
        self::assertStringContainsString('status: skipped', $output);
        self::assertStringContainsString('target records: 1', $output);
        self::assertStringContainsString('DRY RUN: no records were updated.', $output);
        $this->assertDatabaseHas('digest_items', [
            'id' => $item->id,
            'selection_status' => 'skipped',
            'selection_score' => -100,
            'selection_reason' => 'below_skip_threshold',
        ]);
    }

    public function testSkippedRecordsForSourceAreRolledBackToPending(): void
    {
        $item = $this->createDigestItem([
            'article_content_text' => null,
            'analysis_json' => null,
        ]);

        $exitCode = Artisan::call('digestpipe:selection:rollback', [
            '--source' => 'hacker_news',
            '--status' => 'skipped',
        ]);

        self::assertSame(0, $exitCode);
        $output = Artisan::output();
        self::assertStringContainsString('updated records: 1', $output);
        $item->refresh();

        self::assertSame('pending', $item->selection_status);
        self::assertNull($item->selection_score);
        self::assertNull($item->selection_reason);
        self::assertNull($item->selection_result);
        self::assertNull($item->selection_evaluated_at);
        self::assertSame('pending', $item->article_content_status);
        self::assertSame('pending', $item->analysis_status);
    }

    public function testOtherSourcesAreNotChanged(): void
    {
        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'source_name' => 'Hacker News',
        ]);
        $otherSourceItem = $this->createDigestItem([
            'source_key' => 'aws_news',
            'source_name' => 'AWS News',
        ]);

        $exitCode = Artisan::call('digestpipe:selection:rollback', [
            '--source' => 'hacker_news',
            '--status' => 'skipped',
        ]);

        self::assertSame(0, $exitCode);
        $this->assertDatabaseHas('digest_items', [
            'id' => $otherSourceItem->id,
            'selection_status' => 'skipped',
            'selection_score' => -100,
            'selection_reason' => 'below_skip_threshold',
        ]);
    }

    public function testNonSkippedRecordsAreNotChanged(): void
    {
        $selectedItem = $this->createDigestItem([
            'selection_status' => 'selected',
            'selection_score' => 20,
            'selection_reason' => 'above_analysis_threshold',
        ]);

        $exitCode = Artisan::call('digestpipe:selection:rollback', [
            '--source' => 'hacker_news',
            '--status' => 'skipped',
        ]);

        self::assertSame(0, $exitCode);
        $this->assertDatabaseHas('digest_items', [
            'id' => $selectedItem->id,
            'selection_status' => 'selected',
            'selection_score' => 20,
            'selection_reason' => 'above_analysis_threshold',
        ]);
    }

    public function testDownstreamProcessedRecordsAreNotChanged(): void
    {
        $contentStartedItem = $this->createDigestItem([
            'article_content_status' => 'completed',
            'article_content_text' => 'Fetched article content.',
        ]);
        $analysisStartedItem = $this->createDigestItem([
            'analysis_status' => 'completed',
            'analysis_json' => $this->analysisJson(),
        ]);

        $exitCode = Artisan::call('digestpipe:selection:rollback', [
            '--source' => 'hacker_news',
            '--status' => 'skipped',
        ]);

        self::assertSame(0, $exitCode);
        $output = Artisan::output();
        self::assertStringContainsString('skipped due to downstream processing: 2', $output);
        $this->assertDatabaseHas('digest_items', [
            'id' => $contentStartedItem->id,
            'selection_status' => 'skipped',
            'article_content_status' => 'completed',
        ]);
        $this->assertDatabaseHas('digest_items', [
            'id' => $analysisStartedItem->id,
            'selection_status' => 'skipped',
            'analysis_status' => 'completed',
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
            'external_id' => 'selection-rollback-' . $sequence,
            'identity_hash' => hash('sha256', 'selection-rollback-' . $sequence),
            'source_url' => 'https://example.test/articles/' . $sequence,
            'discussion_url' => null,
            'title' => 'Example title ' . $sequence,
            'excerpt' => 'Example excerpt ' . $sequence,
            'published_at' => CarbonImmutable::parse('2026-05-25T10:00:00Z'),
            'fetched_at' => CarbonImmutable::parse('2026-05-25T10:05:00Z'),
            'content_hash' => hash('sha256', 'selection-rollback-content-' . $sequence),
            'selection_status' => 'skipped',
            'selection_score' => -100,
            'selection_reason' => 'below_skip_threshold',
            'selection_result' => [
                'score' => -100,
                'status' => 'skipped',
                'matched_good_keywords' => [],
                'matched_bad_keywords' => ['crypto'],
                'reason' => 'below_skip_threshold',
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
                'brief' => 'Example brief',
                'detailed_summary' => 'Example detailed summary',
                'key_points' => ['Example point'],
                'background' => null,
                'why_it_matters' => null,
                'limitations' => null,
            ],
            'classification' => [
                'content_type' => 'news_article',
                'topics' => ['general'],
                'entities' => [],
                'importance' => 3,
                'confidence' => 0.8,
            ],
        ];
    }
}
