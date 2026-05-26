<?php

namespace App\Console\Commands;

use App\Feeds\FeedSourceRepository;
use App\Models\NewsItem;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * 分析済みニュース記事アイテムの analysis JSON 分布を集計して出力
 *
 * @phpstan-type ContentTypeRow array{content_type: string, count: int}
 * @phpstan-type SourceContentTypeRow array{source_key: string, content_type: string, count: int}
 * @phpstan-type SampleRow array{id: int, source_key: string, content_type: string, title: string}
 */
class AnalysisReportCommand extends Command
{
    protected $signature = 'digestpipe:analysis:report
        {--source= : Filter by source_key}
        {--limit=20 : Limit recent sample rows}';

    protected $description = 'Inspect stored article analysis classification values.';

    private readonly FeedSourceRepository $sources;

    /**
     * Constructor
     *
     * @param FeedSourceRepository $sources
     */
    public function __construct(FeedSourceRepository $sources)
    {
        $this->sources = $sources;

        parent::__construct();
    }

    /**
     * analysis JSON の分類値を集計して表示します。
     *
     * @return int success=0 or invalid=2
     */
    public function handle(): int
    {
        try {
            $source = $this->sourceOption();
            $limit = $this->limitOption();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $items = $this->items($source);

        if ($items === []) {
            $this->info('No completed analysis records found.');

            return self::SUCCESS;
        }

        $this->info('Content type breakdown');
        $this->table(['content_type', 'count'], $this->contentTypeBreakdown($items));

        $this->info('Content type by source');
        $this->table(['source_key', 'content_type', 'count'], $this->contentTypeBySource($items));

        $this->info('Recent samples');
        $this->table(['id', 'source_key', 'content_type', 'title'], $this->recentSamples($items, $limit));

        return self::SUCCESS;
    }

    /**
     * @return list<NewsItem>
     */
    private function items(?string $source): array
    {
        /** @var list<NewsItem> $items */
        $items = NewsItem::query()
            ->where('analysis_status', 'completed')
            ->get()
            ->all();

        $filtered = array_values(array_filter(
            $items,
            fn (NewsItem $item): bool => ($source === null || $item->source_key === $source)
                && $this->contentType($item) !== null,
        ));

        usort($filtered, static fn (NewsItem $left, NewsItem $right): int => $left->id <=> $right->id);

        return $filtered;
    }

    /**
     * @param list<NewsItem> $items
     *
     * @return list<ContentTypeRow>
     */
    private function contentTypeBreakdown(array $items): array
    {
        /** @var array<string, int> $counts */
        $counts = [];

        foreach ($items as $item) {
            $contentType = $this->contentType($item);

            if ($contentType === null) {
                continue;
            }

            $counts[$contentType] = ($counts[$contentType] ?? 0) + 1;
        }

        $rows = [];
        foreach ($counts as $contentType => $count) {
            $rows[] = [
                'content_type' => $contentType,
                'count' => $count,
            ];
        }

        usort($rows, static fn (array $left, array $right): int => [
            -$left['count'],
            $left['content_type'],
        ] <=> [
            -$right['count'],
            $right['content_type'],
        ]);

        return $rows;
    }

    /**
     * @param list<NewsItem> $items
     *
     * @return list<SourceContentTypeRow>
     */
    private function contentTypeBySource(array $items): array
    {
        /** @var array<string, array<string, int>> $counts */
        $counts = [];

        foreach ($items as $item) {
            $contentType = $this->contentType($item);

            if ($contentType === null) {
                continue;
            }

            $counts[$item->source_key] ??= [];
            $counts[$item->source_key][$contentType] = ($counts[$item->source_key][$contentType] ?? 0) + 1;
        }

        $rows = [];
        foreach ($counts as $sourceKey => $sourceCounts) {
            foreach ($sourceCounts as $contentType => $count) {
                $rows[] = [
                    'source_key' => $sourceKey,
                    'content_type' => $contentType,
                    'count' => $count,
                ];
            }
        }

        usort($rows, static fn (array $left, array $right): int => [
            $left['source_key'],
            -$left['count'],
            $left['content_type'],
        ] <=> [
            $right['source_key'],
            -$right['count'],
            $right['content_type'],
        ]);

        return $rows;
    }

    /**
     * @param list<NewsItem> $items
     *
     * @return list<SampleRow>
     */
    private function recentSamples(array $items, int $limit): array
    {
        usort($items, fn (NewsItem $left, NewsItem $right): int => [
            $this->sampleTimestamp($right),
            $right->id,
        ] <=> [
            $this->sampleTimestamp($left),
            $left->id,
        ]);

        $rows = [];
        foreach (array_slice($items, 0, $limit) as $item) {
            $contentType = $this->contentType($item);

            if ($contentType === null) {
                continue;
            }

            $rows[] = [
                'id' => $item->id,
                'source_key' => $item->source_key,
                'content_type' => $contentType,
                'title' => $this->shortTitle($item->title),
            ];
        }

        return $rows;
    }

    private function contentType(NewsItem $item): ?string
    {
        $analysisJson = $item->analysis_json;

        if (! is_array($analysisJson)) {
            return null;
        }

        $classification = $analysisJson['classification'] ?? null;

        if (! is_array($classification)) {
            return null;
        }

        $contentType = $classification['content_type'] ?? null;

        if (! is_string($contentType) || trim($contentType) === '') {
            return null;
        }

        return trim($contentType);
    }

    private function sampleTimestamp(NewsItem $item): int
    {
        if ($item->analyzed_at instanceof CarbonInterface) {
            return $item->analyzed_at->getTimestamp();
        }

        if ($item->updated_at instanceof CarbonInterface) {
            return $item->updated_at->getTimestamp();
        }

        return $item->id;
    }

    private function shortTitle(string $title): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $title) ?? $title);

        if (strlen($title) <= 80) {
            return $title;
        }

        return substr($title, 0, 77) . '...';
    }

    private function sourceOption(): ?string
    {
        $source = $this->stringOption('source');

        if ($source === null) {
            return null;
        }

        foreach ($this->sources->allSources() as $feedSource) {
            if ($feedSource->key === $source) {
                return $source;
            }
        }

        throw new InvalidArgumentException("Unknown source: {$source}");
    }

    private function limitOption(): int
    {
        $value = $this->option('limit');

        if ($value === null || $value === '') {
            return 20;
        }

        $limit = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (! is_int($limit)) {
            throw new InvalidArgumentException('The --limit option must be a positive integer.');
        }

        return $limit;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
