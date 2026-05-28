<?php

namespace App\Admin;

use App\Models\DigestItem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filament dashboard に表示する pipeline health 集計を生成します。
 *
 * @phpstan-type StatusBreakdown array<string, int>
 * @phpstan-type PipelineKpis array{content_failed: int, analysis_failed: int, content_active: int, analysis_active: int, analysis_completed: int}
 * @phpstan-type LatestActivity array{latest_digest_item_created_at: string|null, latest_feed_fetched_at: string|null, latest_article_content_fetched_at: string|null, latest_analysis_completed_at: string|null}
 * @phpstan-type FailedItemRow array{id: int, source_key: string, title: string, failed_stage: string, status: string, error_summary: string|null, updated_at: string|null}
 * @phpstan-type PipelineHealthReport array{period: array{days: int, from: string, to: string}, content_statuses: StatusBreakdown, analysis_statuses: StatusBreakdown, kpis: PipelineKpis, latest: LatestActivity, recent_failed: list<FailedItemRow>}
 */
class PipelineHealthStatsQuery
{
    /**
     * 直近期間の pipeline health dashboard data を返します。
     *
     * @return PipelineHealthReport
     */
    public function report(int $days = 7, int $recentLimit = 5): array
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
            'content_statuses' => $this->statusBreakdown($items, 'article_content_status'),
            'analysis_statuses' => $this->statusBreakdown($items, 'analysis_status'),
            'kpis' => [
                'content_failed' => $this->statusCount($items, 'article_content_status', 'failed'),
                'analysis_failed' => $this->statusCount($items, 'analysis_status', 'failed'),
                'content_active' => $this->statusCount($items, 'article_content_status', 'queued')
                    + $this->statusCount($items, 'article_content_status', 'processing'),
                'analysis_active' => $this->statusCount($items, 'analysis_status', 'queued')
                    + $this->statusCount($items, 'analysis_status', 'processing'),
                'analysis_completed' => $this->statusCount($items, 'analysis_status', 'completed'),
            ],
            'latest' => [
                'latest_digest_item_created_at' => $this->latestTimestamp($items, 'created_at'),
                'latest_feed_fetched_at' => $this->latestTimestamp($items, 'fetched_at'),
                'latest_article_content_fetched_at' => $this->latestTimestamp($items, 'article_content_fetched_at'),
                'latest_analysis_completed_at' => $this->latestTimestamp($items, 'analyzed_at'),
            ],
            'recent_failed' => $this->recentFailedItems($items, $recentLimit),
        ];
    }

    /**
     * @return list<DigestItem>
     */
    private function items(CarbonImmutable $from, CarbonImmutable $to): array
    {
        /** @var list<DigestItem> $items */
        $items = DigestItem::query()
            ->where(static function (Builder $query) use ($from, $to): void {
                $query->where('fetched_at', '>=', $from)
                    ->where('fetched_at', '<=', $to);
            })
            ->orWhere(static function (Builder $query) use ($from, $to): void {
                $query->where('selection_evaluated_at', '>=', $from)
                    ->where('selection_evaluated_at', '<=', $to);
            })
            ->orWhere(static function (Builder $query) use ($from, $to): void {
                $query->where('article_content_fetched_at', '>=', $from)
                    ->where('article_content_fetched_at', '<=', $to);
            })
            ->orWhere(static function (Builder $query) use ($from, $to): void {
                $query->where('analyzed_at', '>=', $from)
                    ->where('analyzed_at', '<=', $to);
            })
            ->get()
            ->all();

        return array_values(array_filter(
            $items,
            fn (DigestItem $item): bool => $this->activityTimestamp($item) >= $from
                && $this->activityTimestamp($item) <= $to
        ));
    }

    private function activityTimestamp(DigestItem $item): CarbonInterface
    {
        $timestamps = array_values(array_filter([
            $item->analyzed_at,
            $item->article_content_fetched_at,
            $item->selection_evaluated_at,
            $item->fetched_at,
        ], static fn (?CarbonInterface $timestamp): bool => $timestamp !== null));

        usort($timestamps, static fn (CarbonInterface $left, CarbonInterface $right): int => $right->getTimestamp() <=> $left->getTimestamp());

        return $timestamps[0];
    }

    /**
     * @param list<DigestItem> $items
     *
     * @return StatusBreakdown
     */
    private function statusBreakdown(array $items, string $field): array
    {
        $counts = [];

        foreach ($items as $item) {
            $status = $this->statusValue($item, $field);
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param list<DigestItem> $items
     */
    private function statusCount(array $items, string $field, string $status): int
    {
        return count(array_filter($items, fn (DigestItem $item): bool => $this->statusValue($item, $field) === $status));
    }

    private function statusValue(DigestItem $item, string $field): string
    {
        $value = match ($field) {
            'article_content_status' => $item->article_content_status,
            'analysis_status' => $item->analysis_status,
            default => null,
        };

        return is_string($value) && $value !== '' ? $value : 'unknown';
    }

    /**
     * @param list<DigestItem> $items
     */
    private function latestTimestamp(array $items, string $field): ?string
    {
        $timestamps = [];

        foreach ($items as $item) {
            $timestamp = match ($field) {
                'created_at' => $item->created_at,
                'fetched_at' => $item->fetched_at,
                'article_content_fetched_at' => $item->article_content_fetched_at,
                'analyzed_at' => $item->analyzed_at,
                default => null,
            };

            if ($timestamp instanceof CarbonInterface) {
                $timestamps[] = $timestamp;
            }
        }

        if ($timestamps === []) {
            return null;
        }

        usort($timestamps, static fn (CarbonInterface $left, CarbonInterface $right): int => $right->getTimestamp() <=> $left->getTimestamp());

        return $timestamps[0]->toJSON();
    }

    /**
     * @param list<DigestItem> $items
     *
     * @return list<FailedItemRow>
     */
    private function recentFailedItems(array $items, int $limit): array
    {
        $failed = array_values(array_filter(
            $items,
            static fn (DigestItem $item): bool => $item->article_content_status === 'failed'
                || $item->analysis_status === 'failed'
        ));

        usort($failed, fn (DigestItem $left, DigestItem $right): int => [
            $this->activityTimestamp($right)->getTimestamp(),
            $right->id,
        ] <=> [
            $this->activityTimestamp($left)->getTimestamp(),
            $left->id,
        ]);

        return array_map(
            fn (DigestItem $item): array => [
                'id' => $item->id,
                'source_key' => $item->source_key,
                'title' => $item->title,
                'failed_stage' => $this->failedStage($item),
                'status' => $this->failedStatus($item),
                'error_summary' => $this->errorSummary($item),
                'updated_at' => $item->updated_at?->toJSON(),
            ],
            array_slice($failed, 0, $limit),
        );
    }

    private function failedStage(DigestItem $item): string
    {
        if ($item->analysis_status === 'failed') {
            return 'analysis';
        }

        return 'article_content';
    }

    private function failedStatus(DigestItem $item): string
    {
        if ($item->analysis_status === 'failed') {
            return $item->analysis_status;
        }

        return $item->article_content_status;
    }

    private function errorSummary(DigestItem $item): ?string
    {
        $error = $item->analysis_status === 'failed'
            ? $item->analysis_error
            : $item->article_content_error;

        if (! is_string($error) || trim($error) === '') {
            return null;
        }

        return mb_substr(trim($error), 0, 160);
    }
}
