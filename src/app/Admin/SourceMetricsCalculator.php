<?php

namespace App\Admin;

use App\Models\DigestItem;

/**
 * Source-level の件数・率メトリクスを計算します。
 *
 * @phpstan-type SourceMetrics array{total: int, selected: int, selected_rate: float, skipped: int, skipped_rate: float, pending: int, pending_rate: float, other: int, content_failed: int, content_failed_rate: float, analysis_failed: int, analysis_failed_rate: float, analysis_completed: int, analysis_completed_rate: float, failure_count: int, failure_rate: float, average_score: float|null}
 */
class SourceMetricsCalculator
{
    /**
     * Digest Item 一覧から source-level メトリクスを生成します。
     *
     * @param list<DigestItem> $items
     *
     * @return SourceMetrics
     */
    public function summarize(array $items): array
    {
        $total = count($items);
        $scores = $this->scores($items);
        $contentFailed = count(array_filter($items, static fn (DigestItem $item): bool => $item->article_content_status === 'failed'));
        $analysisFailed = count(array_filter($items, static fn (DigestItem $item): bool => $item->analysis_status === 'failed'));
        $failureCount = count(array_filter(
            $items,
            static fn (DigestItem $item): bool => $item->article_content_status === 'failed' || $item->analysis_status === 'failed',
        ));
        $selected = $this->selectionStatusCount($items, 'selected');
        $skipped = $this->selectionStatusCount($items, 'skipped');
        $pending = count(array_filter($items, fn (DigestItem $item): bool => $this->isPendingSelection($item)));
        $analysisCompleted = count(array_filter($items, static fn (DigestItem $item): bool => $item->analysis_status === 'completed'));

        return [
            'total' => $total,
            'selected' => $selected,
            'selected_rate' => $this->rate($selected, $total),
            'skipped' => $skipped,
            'skipped_rate' => $this->rate($skipped, $total),
            'pending' => $pending,
            'pending_rate' => $this->rate($pending, $total),
            'other' => count(array_filter($items, fn (DigestItem $item): bool => $this->isOtherSelection($item))),
            'content_failed' => $contentFailed,
            'content_failed_rate' => $this->rate($contentFailed, $total),
            'analysis_failed' => $analysisFailed,
            'analysis_failed_rate' => $this->rate($analysisFailed, $total),
            'analysis_completed' => $analysisCompleted,
            'analysis_completed_rate' => $this->rate($analysisCompleted, $total),
            'failure_count' => $failureCount,
            'failure_rate' => $this->rate($failureCount, $total),
            'average_score' => $scores === [] ? null : round(array_sum($scores) / count($scores), 2),
        ];
    }

    /**
     * 率を percent 表示用の小数値として返します。
     */
    public function rate(int $count, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($count / $total) * 100, 2);
    }

    /**
     * 件数と率を `104 (48.23%)` 形式に整形します。
     */
    public function countRate(int $count, int $total): string
    {
        return sprintf('%d (%.2f%%)', $count, $this->rate($count, $total));
    }

    /**
     * 率を `48.23%` 形式に整形します。
     */
    public function percent(float $rate): string
    {
        return sprintf('%.2f%%', $rate);
    }

    /**
     * average score を表示用に整形します。
     */
    public function score(?float $score): string
    {
        return $score === null ? 'N/A' : sprintf('%.2f', $score);
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
    private function selectionStatusCount(array $items, string $status): int
    {
        return count(array_filter($items, static fn (DigestItem $item): bool => $item->selection_status === $status));
    }

    private function isPendingSelection(DigestItem $item): bool
    {
        return in_array($item->selection_status, ['pending', 'needs_content'], true);
    }

    private function isOtherSelection(DigestItem $item): bool
    {
        return ! in_array($item->selection_status, ['selected', 'skipped', 'pending', 'needs_content'], true);
    }
}
