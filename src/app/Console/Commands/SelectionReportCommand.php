<?php

namespace App\Console\Commands;

use App\Models\NewsItem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

/**
 * ニュース記事アイテムの selection 結果を集計して出力
 *
 * @phpstan-type SummaryRow array{total: int, selected: int, skipped: int, pending: int, other: int, average_score: float|null, min_score: int|null, max_score: int|null}
 * @phpstan-type SourceRow array{source_key: string, total: int, selected: int, skipped: int, pending: int, other: int, average_score: float|null, min_score: int|null, max_score: int|null}
 * @phpstan-type KeywordRow array{keyword: string, count: int}
 * @phpstan-type RecentRow array{id: int, source_key: string, selection_score: int|null, title: string, selection_reason: string|null}
 * @phpstan-type SelectionReport array{period: array{hours: int, from: string|null, to: string|null}, filters: array{source: string|null}, summary: SummaryRow, sources: list<SourceRow>, keywords: array{positive: list<KeywordRow>, negative: list<KeywordRow>}, recent: array{selected: list<RecentRow>, skipped: list<RecentRow>}}
 */
class SelectionReportCommand extends Command
{
    protected $signature = 'digestpipe:selection:report
        {--hours=24 : Report period in hours}
        {--source= : Filter by source_key}
        {--limit=20 : Limit recent selected/skipped item examples}
        {--format=table : Output format: table or json}';

    protected $description = 'Inspect keyword-based selection results.';

    /**
     * selection 結果の運用レポートを出力します。
     *
     * @return int success=0 or invalid=2
     *
     * @throws JsonException
     */
    public function handle(): int
    {
        try {
            $hours = $this->hoursOption();
            $limit = $this->limitOption();
            $format = $this->formatOption();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $to = CarbonImmutable::now();
        $from = $to->subHours($hours);
        $source = $this->sourceOption();
        $items = $this->items($from, $to, $source);
        $report = $this->report($items, $hours, $from, $to, $source, $limit);

        if ($format === 'json') {
            $this->line(json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->writeTableReport($report);

        return self::SUCCESS;
    }

    /**
     * @return list<NewsItem>
     */
    private function items(CarbonImmutable $from, CarbonImmutable $to, ?string $source): array
    {
        /** @var list<NewsItem> $items */
        $items = NewsItem::query()->get()->all();

        return array_values(array_filter(
            $items,
            fn (NewsItem $item): bool => $this->matchesFilters($item, $from, $to, $source)
        ));
    }

    private function matchesFilters(NewsItem $item, CarbonImmutable $from, CarbonImmutable $to, ?string $source): bool
    {
        if ($source !== null && $item->source_key !== $source) {
            return false;
        }

        $timestamp = $this->reportTimestamp($item);

        return $timestamp >= $from && $timestamp <= $to;
    }

    private function reportTimestamp(NewsItem $item): CarbonInterface
    {
        return $item->selection_evaluated_at ?? $item->fetched_at;
    }

    /**
     * @param list<NewsItem> $items
     *
     * @return SelectionReport
     */
    private function report(array $items, int $hours, CarbonImmutable $from, CarbonImmutable $to, ?string $source, int $limit): array
    {
        return [
            'period' => [
                'hours' => $hours,
                'from' => $from->toJSON(),
                'to' => $to->toJSON(),
            ],
            'filters' => [
                'source' => $source,
            ],
            'summary' => $this->summary($items),
            'sources' => $this->sourceBreakdown($items),
            'keywords' => [
                'positive' => $this->keywordBreakdown($items, 'matched_good_keywords'),
                'negative' => $this->keywordBreakdown($items, 'matched_bad_keywords'),
            ],
            'recent' => [
                'selected' => $this->recentItems($items, 'selected', $limit),
                'skipped' => $this->recentItems($items, 'skipped', $limit),
            ],
        ];
    }

    /**
     * @param list<NewsItem> $items
     *
     * @return SummaryRow
     */
    private function summary(array $items): array
    {
        $scores = $this->scores($items);

        return [
            'total' => count($items),
            'selected' => $this->statusCount($items, 'selected'),
            'skipped' => $this->statusCount($items, 'skipped'),
            'pending' => $this->statusCount($items, 'pending'),
            'other' => count(array_filter($items, fn (NewsItem $item): bool => $this->isOtherStatus($item))),
            'average_score' => $scores === [] ? null : round(array_sum($scores) / count($scores), 2),
            'min_score' => $scores === [] ? null : min($scores),
            'max_score' => $scores === [] ? null : max($scores),
        ];
    }

    /**
     * @param list<NewsItem> $items
     *
     * @return list<SourceRow>
     */
    private function sourceBreakdown(array $items): array
    {
        /** @var array<string, list<NewsItem>> $grouped */
        $grouped = [];

        foreach ($items as $item) {
            $grouped[$item->source_key] ??= [];
            $grouped[$item->source_key][] = $item;
        }

        ksort($grouped);

        $rows = [];
        foreach ($grouped as $sourceKey => $sourceItems) {
            $summary = $this->summary($sourceItems);
            $rows[] = array_merge(['source_key' => $sourceKey], $summary);
        }

        return $rows;
    }

    /**
     * @param list<NewsItem> $items
     *
     * @return list<KeywordRow>
     */
    private function keywordBreakdown(array $items, string $field): array
    {
        $counts = [];

        foreach ($items as $item) {
            foreach ($this->matchedKeywords($item, $field) as $keyword) {
                $counts[$keyword] = ($counts[$keyword] ?? 0) + 1;
            }
        }

        arsort($counts);

        $rows = [];
        foreach ($counts as $keyword => $count) {
            $rows[] = [
                'keyword' => $keyword,
                'count' => $count,
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function matchedKeywords(NewsItem $item, string $field): array
    {
        $selectionResult = $item->selection_result;

        if (! is_array($selectionResult)) {
            return [];
        }

        $keywords = $selectionResult[$field] ?? null;

        if (! is_array($keywords)) {
            return [];
        }

        return array_values(array_filter($keywords, static fn (mixed $keyword): bool => is_string($keyword)));
    }

    /**
     * @param list<NewsItem> $items
     *
     * @return list<RecentRow>
     */
    private function recentItems(array $items, string $status, int $limit): array
    {
        $filtered = array_values(array_filter(
            $items,
            static fn (NewsItem $item): bool => $item->selection_status === $status
        ));

        usort($filtered, fn (NewsItem $left, NewsItem $right): int => [
            $this->reportTimestamp($right)->getTimestamp(),
            $right->id,
        ] <=> [
            $this->reportTimestamp($left)->getTimestamp(),
            $left->id,
        ]);

        return array_map(
            static fn (NewsItem $item): array => [
                'id' => $item->id,
                'source_key' => $item->source_key,
                'selection_score' => $item->selection_score,
                'title' => $item->title,
                'selection_reason' => $item->selection_reason,
            ],
            array_slice($filtered, 0, $limit),
        );
    }

    /**
     * @param list<NewsItem> $items
     *
     * @return list<int>
     */
    private function scores(array $items): array
    {
        return array_values(array_filter(
            array_map(static fn (NewsItem $item): ?int => $item->selection_score, $items),
            static fn (?int $score): bool => $score !== null
        ));
    }

    /**
     * @param list<NewsItem> $items
     */
    private function statusCount(array $items, string $status): int
    {
        return count(array_filter($items, static fn (NewsItem $item): bool => $item->selection_status === $status));
    }

    private function isOtherStatus(NewsItem $item): bool
    {
        return ! in_array($item->selection_status, ['selected', 'skipped', 'pending'], true);
    }

    /**
     * @param SelectionReport $report
     */
    private function writeTableReport(array $report): void
    {
        $this->info('Summary');
        $this->table(
            ['total', 'selected', 'skipped', 'pending', 'other', 'average_score', 'min_score', 'max_score'],
            [$report['summary']]
        );

        $this->info('Source breakdown');
        $this->table(
            ['source_key', 'total', 'selected', 'skipped', 'pending', 'other', 'average_score', 'min_score', 'max_score'],
            $report['sources']
        );

        $keywords = $report['keywords'];
        $this->info('Top matched positive keywords');
        $this->table(['keyword', 'count'], $keywords['positive']);

        $this->info('Top matched negative keywords');
        $this->table(['keyword', 'count'], $keywords['negative']);

        $recent = $report['recent'];
        $this->info('Recent skipped items');
        $this->table(['id', 'source_key', 'selection_score', 'title', 'selection_reason'], $recent['skipped']);

        $this->info('Recent selected items');
        $this->table(['id', 'source_key', 'selection_score', 'title', 'selection_reason'], $recent['selected']);
    }

    private function hoursOption(): int
    {
        $hours = filter_var($this->option('hours'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (! is_int($hours)) {
            throw new InvalidArgumentException('The --hours option must be a positive integer.');
        }

        return $hours;
    }

    private function limitOption(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (! is_int($limit)) {
            throw new InvalidArgumentException('The --limit option must be a positive integer.');
        }

        return $limit;
    }

    private function formatOption(): string
    {
        $format = $this->stringOption('format') ?? 'table';

        if (! in_array($format, ['table', 'json'], true)) {
            throw new InvalidArgumentException('The --format option must be table or json.');
        }

        return $format;
    }

    private function sourceOption(): ?string
    {
        return $this->stringOption('source');
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
