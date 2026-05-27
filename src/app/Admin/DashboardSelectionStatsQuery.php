<?php

namespace App\Admin;

use App\Models\DigestItem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Filament dashboard に表示する selection 集計を生成します。
 *
 * @phpstan-type SummaryRow array{total: int, selected: int, skipped: int, pending: int, other: int, average_score: float|null}
 * @phpstan-type SourceRow array{source_key: string, total: int, selected: int, skipped: int, pending: int, other: int, average_score: float|null}
 * @phpstan-type KeywordRow array{keyword: string, count: int}
 * @phpstan-type RecentRow array{id: int, source_key: string, selection_score: int|null, title: string, selection_reason: string|null}
 * @phpstan-type DashboardSelectionReport array{period: array{days: int, from: string, to: string}, summary: SummaryRow, sources: list<SourceRow>, keywords: array{positive: list<KeywordRow>, negative: list<KeywordRow>}, recent: array{selected: list<RecentRow>, skipped: list<RecentRow>}}
 */
class DashboardSelectionStatsQuery
{
    /**
     * 直近期間の selection dashboard data を返します。
     *
     * @return DashboardSelectionReport
     */
    public function report(int $days = 7, int $keywordLimit = 10, int $recentLimit = 5): array
    {
        $to = CarbonImmutable::now();
        $from = $to->subDays($days);
        $items = $this->items($from, $to);

        return [
            'period' => [
                'days' => $days,
                'from' => $from->toJSON(),
                'to' => $to->toJSON(),
            ],
            'summary' => $this->summary($items),
            'sources' => $this->sourceBreakdown($items),
            'keywords' => [
                'positive' => array_slice($this->keywordBreakdown($items, 'matched_good_keywords'), 0, $keywordLimit),
                'negative' => array_slice($this->keywordBreakdown($items, 'matched_bad_keywords'), 0, $keywordLimit),
            ],
            'recent' => [
                'selected' => $this->recentItems($items, 'selected', $recentLimit),
                'skipped' => $this->recentItems($items, 'skipped', $recentLimit),
            ],
        ];
    }

    /**
     * @return list<DigestItem>
     */
    private function items(CarbonImmutable $from, CarbonImmutable $to): array
    {
        /** @var list<DigestItem> $items */
        $items = DigestItem::query()->get()->all();

        return array_values(array_filter(
            $items,
            fn (DigestItem $item): bool => $this->reportTimestamp($item) >= $from
                && $this->reportTimestamp($item) <= $to
        ));
    }

    private function reportTimestamp(DigestItem $item): CarbonInterface
    {
        return $item->selection_evaluated_at
            ?? $item->fetched_at
            ?? $item->created_at
            ?? CarbonImmutable::createFromTimestamp(0);
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
            'total' => count($items),
            'selected' => $this->statusCount($items, 'selected'),
            'skipped' => $this->statusCount($items, 'skipped'),
            'pending' => count(array_filter($items, static fn (DigestItem $item): bool => in_array($item->selection_status, ['pending', 'needs_content'], true))),
            'other' => count(array_filter($items, fn (DigestItem $item): bool => $this->isOtherStatus($item))),
            'average_score' => $scores === [] ? null : round(array_sum($scores) / count($scores), 2),
        ];
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
        foreach ($grouped as $sourceKey => $sourceItems) {
            $summary = $this->summary($sourceItems);
            $rows[] = array_merge(['source_key' => $sourceKey], $summary);
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
                'source_key' => $item->source_key,
                'selection_score' => $item->selection_score,
                'title' => $item->title,
                'selection_reason' => $item->selection_reason,
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
}
