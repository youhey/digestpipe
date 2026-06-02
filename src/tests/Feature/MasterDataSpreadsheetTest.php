<?php

namespace Tests\Feature;

use App\MasterData\MasterDataSpreadsheetService;
use App\Models\FeedSource;
use App\Models\SelectionKeyword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/**
 * @internal
 */
class MasterDataSpreadsheetTest extends TestCase
{
    use RefreshDatabase;

    public function testFeedSourcesCanBeExportedAndImportedAsExcel(): void
    {
        $service = app(MasterDataSpreadsheetService::class);
        $this->createFeedSource('hacker_news', 'Hacker News');

        $xlsx = $service->exportFeedSources();
        self::assertStringStartsWith('PK', $xlsx);

        FeedSource::query()->delete();
        $path = $this->temporaryXlsx($xlsx);

        $result = $service->importFeedSources($path);

        self::assertSame(1, $result->created);
        self::assertSame(0, $result->updated);
        self::assertSame('Hacker News', FeedSource::query()->where('key', 'hacker_news')->firstOrFail()->name);
    }

    public function testFeedSourceImportUpdatesExistingNaturalKey(): void
    {
        $service = app(MasterDataSpreadsheetService::class);
        $this->createFeedSource('hacker_news', 'Hacker News');
        $path = $this->writeWorkbook([
            'key',
            'name',
            'url',
            'language',
            'enabled',
            'analysis_enabled',
            'tier',
            'category',
            'sort_order',
        ], [[
            'hacker_news',
            'Hacker News Updated',
            'https://news.ycombinator.com/rss',
            'en',
            true,
            true,
            'core',
            'tech',
            10,
        ]]);

        $result = $service->importFeedSources($path);

        self::assertSame(0, $result->created);
        self::assertSame(1, $result->updated);
        self::assertSame('Hacker News Updated', FeedSource::query()->where('key', 'hacker_news')->firstOrFail()->name);
    }

    public function testSelectionKeywordsCanBeExportedAndImportedAsExcel(): void
    {
        $service = app(MasterDataSpreadsheetService::class);
        SelectionKeyword::query()->create([
            'keyword' => 'GitHub Actions',
            'type' => 'positive',
            'score' => 12,
            'enabled' => true,
            'locale' => 'en',
            'category' => 'devops',
            'notes' => 'CI keyword',
            'sort_order' => 20,
            'match_mode' => 'exact_phrase',
        ]);

        $xlsx = $service->exportSelectionKeywords('positive');
        self::assertStringStartsWith('PK', $xlsx);

        SelectionKeyword::query()->delete();
        $path = $this->temporaryXlsx($xlsx);

        $result = $service->importSelectionKeywords($path, 'positive');

        $keyword = SelectionKeyword::query()->where('keyword', 'GitHub Actions')->firstOrFail();
        self::assertSame(1, $result->created);
        self::assertSame('positive', $keyword->type);
        self::assertSame(12, $keyword->score);
        self::assertSame('exact_phrase', $keyword->match_mode);
    }

    public function testSelectionKeywordImportKeepsPositiveAndNegativeTypesSeparate(): void
    {
        $service = app(MasterDataSpreadsheetService::class);
        SelectionKeyword::query()->create([
            'keyword' => 'DeFi',
            'type' => 'negative',
            'score' => -80,
            'enabled' => true,
            'locale' => 'en',
            'category' => 'crypto',
            'notes' => null,
            'sort_order' => 30,
            'match_mode' => 'word_boundary',
        ]);
        $path = $this->writeKeywordWorkbook([[
            'DeFi',
            8,
            true,
            'en',
            'finance',
            null,
            10,
            'word_boundary',
        ]]);

        $result = $service->importSelectionKeywords($path, 'positive');

        self::assertSame(1, $result->created);
        self::assertSame(2, DB::table('selection_keywords')->where('keyword', 'DeFi')->count());
        self::assertSame(8, SelectionKeyword::query()->where('keyword', 'DeFi')->where('type', 'positive')->firstOrFail()->score);
        self::assertSame(-80, SelectionKeyword::query()->where('keyword', 'DeFi')->where('type', 'negative')->firstOrFail()->score);
    }

    public function testMasterDataPagesExposeExcelActions(): void
    {
        config(['digestpipe.admin.allowed_emails' => ['admin@example.test']]);
        $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));

        $this->get('/admin/feed-sources')
            ->assertOk()
            ->assertSee('Export Excel')
            ->assertSee('Import Excel');
        $this->get('/admin/positive-keywords')
            ->assertOk()
            ->assertSee('Export Excel')
            ->assertSee('Import Excel');
        $this->get('/admin/negative-keywords')
            ->assertOk()
            ->assertSee('Export Excel')
            ->assertSee('Import Excel');
    }

    private function createFeedSource(string $key, string $name): FeedSource
    {
        return FeedSource::query()->create([
            'key' => $key,
            'name' => $name,
            'url' => 'https://news.ycombinator.com/rss',
            'language' => 'en',
            'enabled' => true,
            'analysis_enabled' => true,
            'tier' => 'core',
            'category' => 'tech',
            'sort_order' => 10,
        ]);
    }

    /**
     * @param list<list<bool|float|int|string|null>> $rows
     */
    private function writeKeywordWorkbook(array $rows): string
    {
        return $this->writeWorkbook([
            'keyword',
            'score',
            'enabled',
            'locale',
            'category',
            'notes',
            'sort_order',
            'match_mode',
        ], $rows);
    }

    /**
     * @param list<string> $headers
     * @param list<list<bool|float|int|string|null>> $rows
     */
    private function writeWorkbook(array $headers, array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'digestpipe-test-master-data-');
        self::assertIsString($path);

        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return $path;
    }

    private function temporaryXlsx(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'digestpipe-test-master-data-');
        self::assertIsString($path);
        file_put_contents($path, $contents);

        return $path;
    }
}
