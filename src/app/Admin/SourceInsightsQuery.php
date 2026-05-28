<?php

namespace App\Admin;

use App\Models\DigestItem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Feed Source を横断比較する source insights data を生成します。
 *
 * @phpstan-import-type SourceMetrics from SourceMetricsCalculator
 *
 * @phpstan-type SourceInsightRow array{source_key: string, total: int, selected: int, selected_rate: float, skipped: int, skipped_rate: float, pending: int, pending_rate: float, analysis_completed: int, analysis_completed_rate: float, content_failed: int, analysis_failed: int, failure_count: int, failure_rate: float, average_score: float|null}
 * @phpstan-type SourceInsightsReport array{period: array{days: int, from: string, to: string}, sources: list<SourceInsightRow>}
 */
class SourceInsightsQuery
{
    private SourceMetricsCalculator $metrics;

    /**
     * Constructor
     *
     * @param SourceMetricsCalculator $metrics
     */
    public function __construct(SourceMetricsCalculator $metrics)
    {
        $this->metrics = $metrics;
    }

    /**
     * Source Insights report を返します。
     *
     * @return SourceInsightsReport
     */
    public function report(int $days = 7, string $sort = 'total'): array
    {
        $to = CarbonImmutable::now();
        $from = $to->subDays($days);
        $items = $this->items($from, $to);
        $sourceKeys = $this->sourceKeys($items);

        $rows = [];
        foreach ($sourceKeys as $sourceKey) {
            $sourceItems = array_values(array_filter(
                $items,
                static fn (DigestItem $item): bool => $item->source_key === $sourceKey,
            ));

            $metrics = $this->metrics->summarize($sourceItems);

            $rows[] = [
                'source_key' => $sourceKey,
                'total' => $metrics['total'],
                'selected' => $metrics['selected'],
                'selected_rate' => $metrics['selected_rate'],
                'skipped' => $metrics['skipped'],
                'skipped_rate' => $metrics['skipped_rate'],
                'pending' => $metrics['pending'],
                'pending_rate' => $metrics['pending_rate'],
                'analysis_completed' => $metrics['analysis_completed'],
                'analysis_completed_rate' => $metrics['analysis_completed_rate'],
                'content_failed' => $metrics['content_failed'],
                'analysis_failed' => $metrics['analysis_failed'],
                'failure_count' => $metrics['failure_count'],
                'failure_rate' => $metrics['failure_rate'],
                'average_score' => $metrics['average_score'],
            ];
        }

        return [
            'period' => [
                'days' => $days,
                'from' => $from->toJSON(),
                'to' => $to->toJSON(),
            ],
            'sources' => $this->sortRows($rows, $sort),
        ];
    }

    /**
     * Source Insights table 表示用に整形した行を返します。
     *
     * @return list<array<string, string|int>>
     */
    public function tableRows(int $days = 7, string $sort = 'total'): array
    {
        return array_map(
            fn (array $row): array => [
                'source_key' => $row['source_key'],
                'total' => $row['total'],
                'selected_rate' => $this->metrics->percent($row['selected_rate']),
                'skipped_rate' => $this->metrics->percent($row['skipped_rate']),
                'pending_rate' => $this->metrics->percent($row['pending_rate']),
                'analysis_completed_rate' => $this->metrics->percent($row['analysis_completed_rate']),
                'failure_rate' => $this->metrics->percent($row['failure_rate']),
                'average_selection_score' => $this->metrics->score($row['average_score']),
            ],
            $this->report($days, $sort)['sources'],
        );
    }

    /**
     * @return list<DigestItem>
     */
    private function items(CarbonImmutable $from, CarbonImmutable $to): array
    {
        /** @var list<DigestItem> $items */
        $items = DigestItem::query()
            ->where(static function (Builder $query) use ($from, $to): void {
                $query->where(static function (Builder $query) use ($from, $to): void {
                    $query->where('fetched_at', '>=', $from)
                        ->where('fetched_at', '<=', $to);
                })->orWhere(static function (Builder $query) use ($from, $to): void {
                    $query->where('selection_evaluated_at', '>=', $from)
                        ->where('selection_evaluated_at', '<=', $to);
                })->orWhere(static function (Builder $query) use ($from, $to): void {
                    $query->where('article_content_fetched_at', '>=', $from)
                        ->where('article_content_fetched_at', '<=', $to);
                })->orWhere(static function (Builder $query) use ($from, $to): void {
                    $query->where('analyzed_at', '>=', $from)
                        ->where('analyzed_at', '<=', $to);
                });
            })
            ->get()
            ->all();

        return array_values(array_filter(
            $items,
            fn (DigestItem $item): bool => $this->reportTimestamp($item) >= $from
                && $this->reportTimestamp($item) <= $to
        ));
    }

    private function reportTimestamp(DigestItem $item): CarbonInterface
    {
        return $item->analyzed_at
            ?? $item->article_content_fetched_at
            ?? $item->selection_evaluated_at
            ?? $item->fetched_at
            ?? $item->updated_at
            ?? $item->created_at
            ?? CarbonImmutable::createFromTimestamp(0);
    }

    /**
     * @param list<DigestItem> $items
     *
     * @return list<string>
     */
    private function sourceKeys(array $items): array
    {
        /** @var list<string> $feedSourceKeys */
        $feedSourceKeys = DB::table('feed_sources')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('key')
            ->all();

        $itemSourceKeys = array_values(array_unique(array_map(
            static fn (DigestItem $item): string => $item->source_key,
            $items,
        )));

        sort($itemSourceKeys);

        return array_values(array_unique(array_merge($feedSourceKeys, $itemSourceKeys)));
    }

    /**
     * @param list<SourceInsightRow> $rows
     *
     * @return list<SourceInsightRow>
     */
    private function sortRows(array $rows, string $sort): array
    {
        $column = match ($sort) {
            'selected-rate' => 'selected_rate',
            'skipped-rate' => 'skipped_rate',
            'pending-rate' => 'pending_rate',
            'analysis-completed-rate' => 'analysis_completed_rate',
            'failure-rate' => 'failure_rate',
            'average-score' => 'average_score',
            default => 'total',
        };

        usort($rows, fn (array $left, array $right): int => [
            -$this->sortValue($left[$column] ?? null),
            $left['source_key'],
        ] <=> [
            -$this->sortValue($right[$column] ?? null),
            $right['source_key'],
        ]);

        return $rows;
    }

    private function sortValue(mixed $value): float
    {
        return is_int($value) || is_float($value) ? (float) $value : -INF;
    }
}
