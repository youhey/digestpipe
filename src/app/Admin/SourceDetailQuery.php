<?php

namespace App\Admin;

use App\Models\DigestItem;
use App\Models\FeedSource;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Feed Source detail page に表示する source-specific 集計を生成します。
 *
 * @phpstan-type SourceMetadata array{key: string, name: string, url: string, language: string, enabled: bool, analysis_enabled: bool, tier: string, category: string}
 * @phpstan-type SourceKpis array{total: int, selected: int, skipped: int, pending: int, other: int, content_failed: int, analysis_failed: int, average_score: float|null}
 * @phpstan-type StatusRow array{status: string, count: int}
 * @phpstan-type KeywordRow array{keyword: string, count: int}
 * @phpstan-type ContentTypeRow array{content_type: string, count: int}
 * @phpstan-type RecentRow array{id: int, title: string, selection_score: int|null, selection_status: string, article_content_status: string, analysis_status: string, updated_at: string|null}
 * @phpstan-type SourceDetailReport array{period: array{days: int, from: string, to: string}, source: SourceMetadata, kpis: SourceKpis, selection_statuses: list<StatusRow>, article_content_statuses: list<StatusRow>, analysis_statuses: list<StatusRow>, keywords: array{positive: list<KeywordRow>, negative: list<KeywordRow>}, content_types: list<ContentTypeRow>, recent: array{selected: list<RecentRow>, skipped: list<RecentRow>, failed: list<RecentRow>}}
 */
class SourceDetailQuery
{
    /**
     * Source key から Feed Source を解決して source detail data を返します。
     *
     * @return SourceDetailReport
     */
    public function reportForSourceKey(string $sourceKey, int $days = 7, int $keywordLimit = 100, int $recentLimit = 100): array
    {
        /** @var FeedSource $source */
        $source = FeedSource::query()
            ->where('key', $sourceKey)
            ->firstOrFail();

        return $this->report($source, $days, $keywordLimit, $recentLimit);
    }

    /**
     * 指定した Feed Source の source detail data を返します。
     *
     * @return SourceDetailReport
     */
    public function report(FeedSource $source, int $days = 7, int $keywordLimit = 100, int $recentLimit = 100): array
    {
        $to = CarbonImmutable::now();
        $from = $to->subDays($days);
        $items = $this->items($source->key, $from, $to);

        return [
            'period' => [
                'days' => $days,
                'from' => $from->toJSON(),
                'to' => $to->toJSON(),
            ],
            'source' => [
                'key' => $source->key,
                'name' => $source->name,
                'url' => $source->url,
                'language' => $source->language,
                'enabled' => $source->enabled,
                'analysis_enabled' => $source->analysis_enabled,
                'tier' => $source->tier,
                'category' => $source->category,
            ],
            'kpis' => $this->kpis($items),
            'selection_statuses' => $this->selectionStatuses($items),
            'article_content_statuses' => $this->statusBreakdown($items, static fn (DigestItem $item): string => $item->article_content_status),
            'analysis_statuses' => $this->statusBreakdown($items, static fn (DigestItem $item): string => $item->analysis_status),
            'keywords' => [
                'positive' => array_slice($this->keywordBreakdown($items, 'matched_good_keywords'), 0, $keywordLimit),
                'negative' => array_slice($this->keywordBreakdown($items, 'matched_bad_keywords'), 0, $keywordLimit),
            ],
            'content_types' => $this->contentTypeBreakdown($items),
            'recent' => [
                'selected' => $this->recentItems($items, static fn (DigestItem $item): bool => $item->selection_status === 'selected', $recentLimit),
                'skipped' => $this->recentItems($items, static fn (DigestItem $item): bool => $item->selection_status === 'skipped', $recentLimit),
                'failed' => $this->recentItems($items, static fn (DigestItem $item): bool => $item->article_content_status === 'failed' || $item->analysis_status === 'failed', $recentLimit),
            ],
        ];
    }

    /**
     * @return list<DigestItem>
     */
    private function items(string $sourceKey, CarbonImmutable $from, CarbonImmutable $to): array
    {
        /** @var list<DigestItem> $items */
        $items = DigestItem::query()
            ->where('source_key', $sourceKey)
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

    /**
     * @param list<DigestItem> $items
     *
     * @return SourceKpis
     */
    private function kpis(array $items): array
    {
        $scores = $this->scores($items);

        return [
            'total' => count($items),
            'selected' => $this->selectionStatusCount($items, 'selected'),
            'skipped' => $this->selectionStatusCount($items, 'skipped'),
            'pending' => count(array_filter($items, fn (DigestItem $item): bool => $this->isPendingSelection($item))),
            'other' => count(array_filter($items, fn (DigestItem $item): bool => $this->isOtherSelection($item))),
            'content_failed' => count(array_filter($items, static fn (DigestItem $item): bool => $item->article_content_status === 'failed')),
            'analysis_failed' => count(array_filter($items, static fn (DigestItem $item): bool => $item->analysis_status === 'failed')),
            'average_score' => $scores === [] ? null : round(array_sum($scores) / count($scores), 2),
        ];
    }

    /**
     * @param list<DigestItem> $items
     *
     * @return list<StatusRow>
     */
    private function selectionStatuses(array $items): array
    {
        $kpis = $this->kpis($items);

        return [
            ['status' => 'selected', 'count' => $kpis['selected']],
            ['status' => 'skipped', 'count' => $kpis['skipped']],
            ['status' => 'pending', 'count' => $kpis['pending']],
            ['status' => 'other', 'count' => $kpis['other']],
        ];
    }

    /**
     * @param list<DigestItem> $items
     * @param callable(DigestItem): string $statusResolver
     *
     * @return list<StatusRow>
     */
    private function statusBreakdown(array $items, callable $statusResolver): array
    {
        /** @var array<string, int> $counts */
        $counts = [];

        foreach ($items as $item) {
            $status = $statusResolver($item);
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        ksort($counts);

        $rows = [];
        foreach ($counts as $status => $count) {
            $rows[] = [
                'status' => $status,
                'count' => $count,
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
        /** @var array<string, int> $counts */
        $counts = [];

        foreach ($items as $item) {
            foreach ($this->matchedKeywords($item, $field) as $keyword) {
                $counts[$keyword] = ($counts[$keyword] ?? 0) + 1;
            }
        }

        $rows = [];
        foreach ($counts as $keyword => $count) {
            $rows[] = [
                'keyword' => $keyword,
                'count' => $count,
            ];
        }

        usort($rows, static fn (array $left, array $right): int => [
            -$left['count'],
            $left['keyword'],
        ] <=> [
            -$right['count'],
            $right['keyword'],
        ]);

        return $rows;
    }

    /**
     * @param list<DigestItem> $items
     *
     * @return list<ContentTypeRow>
     */
    private function contentTypeBreakdown(array $items): array
    {
        /** @var array<string, int> $counts */
        $counts = [];

        foreach ($items as $item) {
            if ($item->analysis_status !== 'completed') {
                continue;
            }

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
     * @param list<DigestItem> $items
     * @param callable(DigestItem): bool $filter
     *
     * @return list<RecentRow>
     */
    private function recentItems(array $items, callable $filter, int $limit): array
    {
        $filtered = array_values(array_filter($items, $filter));

        usort($filtered, fn (DigestItem $left, DigestItem $right): int => [
            $this->reportTimestamp($right)->getTimestamp(),
            $right->id,
        ] <=> [
            $this->reportTimestamp($left)->getTimestamp(),
            $left->id,
        ]);

        return array_map(
            fn (DigestItem $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'selection_score' => $item->selection_score,
                'selection_status' => $item->selection_status,
                'article_content_status' => $item->article_content_status,
                'analysis_status' => $item->analysis_status,
                'updated_at' => $item->updated_at?->toDateTimeString(),
            ],
            array_slice($filtered, 0, $limit),
        );
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

        return array_values(array_filter($keywords, static fn (mixed $keyword): bool => is_string($keyword) && trim($keyword) !== ''));
    }

    private function contentType(DigestItem $item): ?string
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
