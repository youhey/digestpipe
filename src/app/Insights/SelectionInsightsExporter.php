<?php

namespace App\Insights;

use App\Feeds\FeedSourceRepository;
use App\Models\DigestItem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Selection behavior を ChatGPT へ渡しやすい Markdown として export します。
 *
 * @phpstan-type SummaryRow array{total_digest_items: int, selected: int, skipped: int, pending: int, other: int, average_selection_score: float|null}
 * @phpstan-type SourceRow array{source: string, total: int, selected: int, skipped: int, pending: int, other: int, avg_score: float|null}
 * @phpstan-type KeywordRow array{keyword: string, count: int}
 * @phpstan-type RecentRow array{id: int, source: string, score: int|null, reason: string|null, title: string}
 */
class SelectionInsightsExporter
{
    private FeedSourceRepository $sources;

    /**
     * Constructor
     *
     * @param FeedSourceRepository $sources
     */
    public function __construct(FeedSourceRepository $sources)
    {
        $this->sources = $sources;
    }

    /**
     * Selection insights Markdown export を生成します。
     */
    public function export(InsightsExportOptions $options): InsightsExportResult
    {
        $this->validateOptions($options);

        $to = CarbonImmutable::now();
        $from = $to->subDays($options->days);
        $items = $this->items($from, $to, $options->source);
        $content = $this->markdown($items, $options, $from, $to);

        return new InsightsExportResult(
            'digestpipe-insights-' . $to->format('Ymd-His') . '.md',
            'text/markdown; charset=UTF-8',
            $content,
        );
    }

    private function validateOptions(InsightsExportOptions $options): void
    {
        if ($options->format !== 'markdown') {
            throw new InvalidArgumentException('The --format option must be markdown.');
        }

        if ($options->days < 1) {
            throw new InvalidArgumentException('The --days option must be a positive integer.');
        }

        if ($options->sampleLimit < 1) {
            throw new InvalidArgumentException('The --sample-limit option must be a positive integer.');
        }

        if ($options->keywordLimit < 1) {
            throw new InvalidArgumentException('The --keyword-limit option must be a positive integer.');
        }

        if ($options->source !== null && ! in_array($options->source, $this->sourceKeys(), true)) {
            throw new InvalidArgumentException("Unknown source: {$options->source}");
        }
    }

    /**
     * @return list<string>
     */
    private function sourceKeys(): array
    {
        return array_map(
            static fn ($source): string => $source->key,
            $this->sources->allSources(),
        );
    }

    /**
     * @return list<DigestItem>
     */
    private function items(CarbonImmutable $from, CarbonImmutable $to, ?string $source): array
    {
        /** @var list<DigestItem> $items */
        $items = DigestItem::query()->get()->all();

        return array_values(array_filter(
            $items,
            fn (DigestItem $item): bool => $this->matchesFilters($item, $from, $to, $source)
        ));
    }

    private function matchesFilters(DigestItem $item, CarbonImmutable $from, CarbonImmutable $to, ?string $source): bool
    {
        if ($source !== null && $item->source_key !== $source) {
            return false;
        }

        $timestamp = $this->reportTimestamp($item);

        return $timestamp >= $from && $timestamp <= $to;
    }

    private function reportTimestamp(DigestItem $item): CarbonInterface
    {
        return $item->selection_evaluated_at
            ?? $item->updated_at
            ?? $item->created_at
            ?? CarbonImmutable::createFromTimestamp(0);
    }

    /**
     * @param list<DigestItem> $items
     */
    private function markdown(array $items, InsightsExportOptions $options, CarbonImmutable $from, CarbonImmutable $to): string
    {
        $lines = [
            '# digestpipe Insights Export',
            '',
            'Generated at: ' . $to->toJSON(),
            'Period: last ' . $options->days . ' days',
            'Period from: ' . $from->toJSON(),
            'Period to: ' . $to->toJSON(),
            'Timestamp basis: selection_evaluated_at, then updated_at, then created_at',
            'Source filter: ' . ($options->source ?? 'all'),
            'Purpose: ChatGPT-assisted selection analysis',
            '',
            '## Suggested Analysis Prompt',
            '',
            'Please analyze this digestpipe export and identify:',
            '- sources with abnormal selected/skipped/pending ratios',
            '- keywords that may be causing false positives or false negatives',
            '- sources that may need different selection rules',
            '- skipped items that look potentially valuable',
            '- recommended tuning actions',
            '',
            '## Summary',
            '',
            $this->markdownTable(['metric', 'value'], $this->summaryRows($this->summary($items))),
            '',
            '## Source Breakdown',
            '',
            $this->markdownTable(['source', 'total', 'selected', 'skipped', 'pending', 'other', 'avg_score'], $this->sourceBreakdown($items)),
            '',
            '## Top Positive Keywords',
            '',
            $this->markdownTable(['keyword', 'count'], array_slice($this->keywordBreakdown($items, 'matched_good_keywords'), 0, $options->keywordLimit)),
            '',
            '## Top Negative Keywords',
            '',
            $this->markdownTable(['keyword', 'count'], array_slice($this->keywordBreakdown($items, 'matched_bad_keywords'), 0, $options->keywordLimit)),
            '',
            '## Recent Skipped Items',
            '',
            $this->markdownTable(['id', 'source', 'score', 'reason', 'title'], $this->recentItems($items, 'skipped', $options->sampleLimit)),
            '',
            '## Recent Selected Items',
            '',
            $this->markdownTable(['id', 'source', 'score', 'reason', 'title'], $this->recentItems($items, 'selected', $options->sampleLimit)),
            '',
        ];

        return implode("\n", $lines);
    }

    /**
     * @param list<DigestItem> $items
     *
     * @return SummaryRow
     */
    private function summary(array $items): array
    {
        $scores = $this->scores($items);

        return [
            'total_digest_items' => count($items),
            'selected' => $this->statusCount($items, 'selected'),
            'skipped' => $this->statusCount($items, 'skipped'),
            'pending' => count(array_filter($items, static fn (DigestItem $item): bool => in_array($item->selection_status, ['pending', 'needs_content'], true))),
            'other' => count(array_filter($items, fn (DigestItem $item): bool => $this->isOtherStatus($item))),
            'average_selection_score' => $scores === [] ? null : round(array_sum($scores) / count($scores), 2),
        ];
    }

    /**
     * @param SummaryRow $summary
     *
     * @return list<array{metric: string, value: int|float|string}>
     */
    private function summaryRows(array $summary): array
    {
        $rows = [];

        foreach ($summary as $metric => $value) {
            $rows[] = [
                'metric' => $metric,
                'value' => $this->displayValue($value),
            ];
        }

        return $rows;
    }

    /**
     * @param list<DigestItem> $items
     *
     * @return list<SourceRow>
     */
    private function sourceBreakdown(array $items): array
    {
        /** @var array<string, list<DigestItem>> $grouped */
        $grouped = [];

        foreach ($items as $item) {
            $grouped[$item->source_key] ??= [];
            $grouped[$item->source_key][] = $item;
        }

        ksort($grouped);

        $rows = [];
        foreach ($grouped as $source => $sourceItems) {
            $summary = $this->summary($sourceItems);
            $rows[] = [
                'source' => $source,
                'total' => $summary['total_digest_items'],
                'selected' => $summary['selected'],
                'skipped' => $summary['skipped'],
                'pending' => $summary['pending'],
                'other' => $summary['other'],
                'avg_score' => $summary['average_selection_score'],
            ];
        }

        return $rows;
    }

    /**
     * @param list<DigestItem> $items
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
    private function matchedKeywords(DigestItem $item, string $field): array
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
     * @param list<DigestItem> $items
     *
     * @return list<RecentRow>
     */
    private function recentItems(array $items, string $status, int $limit): array
    {
        $filtered = array_values(array_filter(
            $items,
            static fn (DigestItem $item): bool => $item->selection_status === $status
        ));

        usort($filtered, fn (DigestItem $left, DigestItem $right): int => [
            $this->reportTimestamp($right)->getTimestamp(),
            $right->id,
        ] <=> [
            $this->reportTimestamp($left)->getTimestamp(),
            $left->id,
        ]);

        return array_map(
            static fn (DigestItem $item): array => [
                'id' => $item->id,
                'source' => $item->source_key,
                'score' => $item->selection_score,
                'reason' => $item->selection_reason,
                'title' => $item->title,
            ],
            array_slice($filtered, 0, $limit),
        );
    }

    /**
     * @param list<DigestItem> $items
     *
     * @return list<int>
     */
    private function scores(array $items): array
    {
        return array_values(array_filter(
            array_map(static fn (DigestItem $item): ?int => $item->selection_score, $items),
            static fn (?int $score): bool => $score !== null
        ));
    }

    /**
     * @param list<DigestItem> $items
     */
    private function statusCount(array $items, string $status): int
    {
        return count(array_filter($items, static fn (DigestItem $item): bool => $item->selection_status === $status));
    }

    private function isOtherStatus(DigestItem $item): bool
    {
        return ! in_array($item->selection_status, ['selected', 'skipped', 'pending', 'needs_content'], true);
    }

    /**
     * @param list<string> $headers
     * @param list<array<string, mixed>> $rows
     */
    private function markdownTable(array $headers, array $rows): string
    {
        $lines = [
            '| ' . implode(' | ', array_map(fn (string $header): string => $this->escapeCell($header), $headers)) . ' |',
            '| ' . implode(' | ', array_map(fn (string $header): string => $this->alignment($header), $headers)) . ' |',
        ];

        foreach ($rows as $row) {
            $lines[] = '| ' . implode(' | ', array_map(
                fn (string $header): string => $this->escapeCell($this->displayValue($row[$header] ?? null)),
                $headers,
            )) . ' |';
        }

        return implode("\n", $lines);
    }

    private function alignment(string $header): string
    {
        return in_array($header, ['count', 'id', 'total', 'selected', 'skipped', 'pending', 'other', 'score', 'avg_score', 'value'], true)
            ? '---:'
            : '---';
    }

    private function displayValue(mixed $value): string
    {
        if ($value === null) {
            return 'n/a';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string) $value;
        }

        return 'n/a';
    }

    private function escapeCell(string $value): string
    {
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace('|', '\|', $value);

        return trim($value);
    }
}
