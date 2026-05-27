<?php

namespace Tests\Feature;

use App\Models\DigestItem;
use Carbon\CarbonImmutable;
use Database\Seeders\FeedSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * @internal
 */
class DigestExportCommandTest extends TestCase
{
    use RefreshDatabase;

    private int $digestItemSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeedSourceSeeder::class);
    }

    public function testJsonExportIncludesOnlyCompletedAnalysisItems(): void
    {
        $readyItem = $this->createDigestItem([
            'analysis_status' => 'completed',
            'analysis_json' => $this->analysisJson('Ready brief', ['technology']),
            'analysis_model' => 'gpt-test',
            'analyzed_at' => CarbonImmutable::parse('2026-05-24 12:02:00'),
        ]);

        foreach (['pending', 'queued', 'processing', 'failed', 'skipped'] as $status) {
            $this->createDigestItem([
                'analysis_status' => $status,
                'analysis_json' => $this->analysisJson('Excluded brief', ['technology']),
            ]);
        }

        $this->createDigestItem([
            'analysis_status' => 'completed',
            'analysis_json' => null,
        ]);

        $document = $this->exportJson();
        $data = $this->dataList($document);
        $meta = $this->meta($document);

        self::assertSame(1, $meta['count'] ?? null);
        self::assertSame($readyItem->id, $data[0]['id'] ?? null);
    }

    public function testJsonExportIncludesStableMetadataAndStoredAnalysisWithoutRawArticleContent(): void
    {
        $analysis = $this->analysisJson('Stable brief', ['technology']);
        $item = $this->createDigestItem([
            'source_key' => 'hacker_news',
            'source_name' => 'Hacker News',
            'source_url' => 'https://example.test/article',
            'discussion_url' => 'https://news.ycombinator.com/item?id=123',
            'title' => 'Original title',
            'published_at' => CarbonImmutable::parse('2026-05-24 00:00:00'),
            'fetched_at' => CarbonImmutable::parse('2026-05-24 00:01:00'),
            'analysis_status' => 'completed',
            'analysis_json' => $analysis,
            'analysis_model' => 'gpt-test',
            'analyzed_at' => CarbonImmutable::parse('2026-05-24 00:02:00'),
            'article_content_text' => 'Raw extracted article content must not be exported.',
        ]);

        $document = $this->exportJson();
        $record = $this->dataList($document)[0];
        $source = $this->section($record, 'source');
        $article = $this->section($record, 'article');
        $processing = $this->section($record, 'processing');

        self::assertSame($item->id, $record['id'] ?? null);
        self::assertSame('hacker_news', $source['key'] ?? null);
        self::assertSame('Hacker News', $source['name'] ?? null);
        self::assertSame('https://news.ycombinator.com/rss', $source['feed_url'] ?? null);
        self::assertSame('Original title', $article['title'] ?? null);
        self::assertSame('https://example.test/article', $article['url'] ?? null);
        self::assertSame('https://news.ycombinator.com/item?id=123', $article['discussion_url'] ?? null);
        self::assertSame('2026-05-24T00:00:00.000000Z', $article['published_at'] ?? null);
        self::assertEquals($analysis, $record['analysis'] ?? null);
        self::assertSame('gpt-test', $processing['analysis_model'] ?? null);
        self::assertSame('2026-05-24T00:02:00.000000Z', $processing['analyzed_at'] ?? null);
        self::assertArrayNotHasKey('article_content_text', $record);
    }

    public function testJsonlExportOutputsOneDigestRecordPerLine(): void
    {
        $firstItem = $this->createDigestItem([
            'analysis_status' => 'completed',
            'analysis_json' => $this->analysisJson('First brief', ['technology']),
            'published_at' => CarbonImmutable::parse('2026-05-24 12:00:00'),
        ]);
        $secondItem = $this->createDigestItem([
            'analysis_status' => 'completed',
            'analysis_json' => $this->analysisJson('Second brief', ['business']),
            'published_at' => CarbonImmutable::parse('2026-05-23 12:00:00'),
        ]);

        $exitCode = Artisan::call('digestpipe:digests:export', [
            '--format' => 'jsonl',
            '--limit' => 20,
        ]);

        self::assertSame(0, $exitCode);
        $lines = array_values(array_filter(
            explode("\n", trim(Artisan::output())),
            static fn (string $line): bool => $line !== ''
        ));
        self::assertCount(2, $lines);

        $firstRecord = $this->decodeJsonObject($lines[0]);
        $secondRecord = $this->decodeJsonObject($lines[1]);

        self::assertSame($firstItem->id, $firstRecord['id'] ?? null);
        self::assertSame($secondItem->id, $secondRecord['id'] ?? null);
    }

    public function testFiltersAndLimitAreApplied(): void
    {
        $matchingItem = $this->createDigestItem([
            'source_key' => 'hacker_news',
            'source_name' => 'Hacker News',
            'published_at' => CarbonImmutable::parse('2026-05-20 12:00:00'),
            'analysis_status' => 'completed',
            'analysis_json' => $this->analysisJson('Matching brief', ['technology'], 'news_article'),
        ]);
        $this->createDigestItem([
            'source_key' => 'reuters_top',
            'source_name' => 'Reuters Top News',
            'published_at' => CarbonImmutable::parse('2026-05-20 12:00:00'),
            'analysis_status' => 'completed',
            'analysis_json' => $this->analysisJson('Wrong source', ['technology'], 'news_article'),
        ]);
        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'source_name' => 'Hacker News',
            'published_at' => CarbonImmutable::parse('2026-04-30 12:00:00'),
            'analysis_status' => 'completed',
            'analysis_json' => $this->analysisJson('Wrong date', ['technology'], 'news_article'),
        ]);
        $this->createDigestItem([
            'source_key' => 'hacker_news',
            'source_name' => 'Hacker News',
            'published_at' => CarbonImmutable::parse('2026-05-20 12:00:00'),
            'analysis_status' => 'completed',
            'analysis_json' => $this->analysisJson('Wrong topic', ['business'], 'news_article'),
        ]);

        $document = $this->exportJson([
            '--source' => 'hacker_news',
            '--topic' => 'technology',
            '--content-type' => 'news_article',
            '--from' => '2026-05-01',
            '--to' => '2026-05-24',
            '--limit' => 1,
        ]);
        $data = $this->dataList($document);
        $meta = $this->meta($document);

        self::assertSame(1, $meta['count'] ?? null);
        self::assertSame(1, $meta['limit'] ?? null);
        self::assertSame($matchingItem->id, $data[0]['id'] ?? null);
    }

    public function testInvalidFormatReturnsClearCommandError(): void
    {
        $exitCode = Artisan::call('digestpipe:digests:export', [
            '--format' => 'xml',
        ]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('The --format option must be json or jsonl.', Artisan::output());
    }

    public function testJsonExportReturnsValidEmptyDocumentWhenNoRecordsMatch(): void
    {
        $document = $this->exportJson([
            '--source' => 'missing_source',
        ]);

        self::assertSame([], $document['data'] ?? null);
        self::assertSame([
            'count' => 0,
            'limit' => 20,
        ], $document['meta'] ?? null);
    }

    /**
     * @param array<string, bool|int|string> $options
     *
     * @return array<string, mixed>
     */
    private function exportJson(array $options = []): array
    {
        $exitCode = Artisan::call('digestpipe:digests:export', array_merge([
            '--format' => 'json',
        ], $options));

        self::assertSame(0, $exitCode);

        return $this->decodeJsonObject(Artisan::output());
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return list<array<string, mixed>>
     */
    private function dataList(array $document): array
    {
        $data = $document['data'] ?? null;
        self::assertIsArray($data);

        /** @var list<array<string, mixed>> $data */
        return $data;
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return array<string, mixed>
     */
    private function meta(array $document): array
    {
        $meta = $document['meta'] ?? null;
        self::assertIsArray($meta);

        /** @var array<string, mixed> $meta */
        return $meta;
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return array<string, mixed>
     */
    private function section(array $record, string $key): array
    {
        $section = $record[$key] ?? null;
        self::assertIsArray($section);

        /** @var array<string, mixed> $section */
        return $section;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(string $json): array
    {
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
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
            'external_id' => 'digest-export-' . $sequence,
            'identity_hash' => hash('sha256', 'digest-export-' . $sequence),
            'source_url' => 'https://news.example.test/' . $sequence,
            'discussion_url' => null,
            'title' => 'Example title ' . $sequence,
            'excerpt' => 'Example excerpt ' . $sequence,
            'published_at' => CarbonImmutable::parse('2026-05-23 12:00:00'),
            'fetched_at' => CarbonImmutable::parse('2026-05-23 12:05:00'),
            'content_hash' => hash('sha256', 'digest-content-' . $sequence),
            'article_content_status' => 'completed',
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
     * @param list<string> $topics
     *
     * @return array<string, mixed>
     */
    private function analysisJson(string $brief, array $topics, string $contentType = 'news_article'): array
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
                'content_type' => $contentType,
                'topics' => $topics,
                'entities' => ['Example Entity'],
                'importance' => 3,
                'confidence' => 0.8,
            ],
        ];
    }
}
