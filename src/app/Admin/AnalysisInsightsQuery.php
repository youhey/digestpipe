<?php

namespace App\Admin;

use App\Models\DigestItem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * 分析結果 JSON の品質確認用 dashboard data を生成します。
 *
 * @phpstan-type ContentTypeRow array{content_type: string, count: int}
 * @phpstan-type SourceContentTypeRow array{source_key: string, content_type: string, count: int}
 * @phpstan-type SampleRow array{id: int, source_key: string, content_type: string|null, confidence: float|null, importance: int|null, title: string}
 * @phpstan-type LowConfidenceRow array{id: int, source_key: string, confidence: float, content_type: string|null, title: string, limitations: string|null}
 * @phpstan-type BucketRow array{label: string, count: int}
 * @phpstan-type AnalysisInsightsReport array{period: array{days: int, from: string, to: string}, content_types: list<ContentTypeRow>, content_types_by_source: list<SourceContentTypeRow>, recent_samples: list<SampleRow>, confidence_distribution: list<BucketRow>, importance_distribution: list<BucketRow>, low_confidence_items: list<LowConfidenceRow>}
 */
class AnalysisInsightsQuery
{
    private const LOW_CONFIDENCE_THRESHOLD = 0.6;

    /**
     * 直近期間の analysis insights data を返します。
     *
     * @return AnalysisInsightsReport
     */
    public function report(int $days = 30, int $sampleLimit = 20, int $lowConfidenceLimit = 20): array
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
            'content_types' => $this->contentTypeBreakdown($items),
            'content_types_by_source' => $this->contentTypeBySource($items),
            'recent_samples' => $this->recentSamples($items, $sampleLimit),
            'confidence_distribution' => $this->confidenceDistribution($items),
            'importance_distribution' => $this->importanceDistribution($items),
            'low_confidence_items' => $this->lowConfidenceItems($items, $lowConfidenceLimit),
        ];
    }

    /**
     * @return list<DigestItem>
     */
    private function items(CarbonImmutable $from, CarbonImmutable $to): array
    {
        /** @var list<DigestItem> $items */
        $items = DigestItem::query()
            ->where('analysis_status', 'completed')
            ->where(static function (Builder $query) use ($from, $to): void {
                $query->where(static function (Builder $query) use ($from, $to): void {
                    $query->where('analyzed_at', '>=', $from)
                        ->where('analyzed_at', '<=', $to);
                })->orWhere(static function (Builder $query) use ($from, $to): void {
                    $query->where('analyzed_at', '=', null)
                        ->where('updated_at', '>=', $from)
                        ->where('updated_at', '<=', $to);
                });
            })
            ->get()
            ->all();

        return array_values(array_filter(
            $items,
            fn (DigestItem $item): bool => $this->analysisTimestamp($item) >= $from
                && $this->analysisTimestamp($item) <= $to
        ));
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
     * @param list<DigestItem> $items
     *
     * @return list<SampleRow>
     */
    private function recentSamples(array $items, int $limit): array
    {
        usort($items, fn (DigestItem $left, DigestItem $right): int => [
            $this->analysisTimestamp($right)->getTimestamp(),
            $right->id,
        ] <=> [
            $this->analysisTimestamp($left)->getTimestamp(),
            $left->id,
        ]);

        return array_map(
            fn (DigestItem $item): array => [
                'id' => $item->id,
                'source_key' => $item->source_key,
                'content_type' => $this->contentType($item),
                'confidence' => $this->confidence($item),
                'importance' => $this->importance($item),
                'title' => $item->title,
            ],
            array_slice($items, 0, $limit),
        );
    }

    /**
     * @param list<DigestItem> $items
     *
     * @return list<BucketRow>
     */
    private function confidenceDistribution(array $items): array
    {
        $buckets = [
            '0.0 - 0.2' => 0,
            '0.3 - 0.5' => 0,
            '0.6 - 0.8' => 0,
            '0.9 - 1.0' => 0,
        ];

        foreach ($items as $item) {
            $confidence = $this->confidence($item);

            if ($confidence === null) {
                continue;
            }

            ++$buckets[$this->confidenceBucket($confidence)];
        }

        return $this->bucketRows($buckets);
    }

    /**
     * @param list<DigestItem> $items
     *
     * @return list<BucketRow>
     */
    private function importanceDistribution(array $items): array
    {
        $buckets = [
            '1' => 0,
            '2' => 0,
            '3' => 0,
            '4' => 0,
            '5' => 0,
        ];

        foreach ($items as $item) {
            $importance = $this->importance($item);

            if ($importance === null || ! array_key_exists((string) $importance, $buckets)) {
                continue;
            }

            ++$buckets[(string) $importance];
        }

        return $this->bucketRows($buckets);
    }

    /**
     * @param list<DigestItem> $items
     *
     * @return list<LowConfidenceRow>
     */
    private function lowConfidenceItems(array $items, int $limit): array
    {
        $filtered = array_values(array_filter(
            $items,
            fn (DigestItem $item): bool => ($this->confidence($item) ?? 1.0) < self::LOW_CONFIDENCE_THRESHOLD
        ));

        usort($filtered, fn (DigestItem $left, DigestItem $right): int => [
            $this->analysisTimestamp($right)->getTimestamp(),
            $right->id,
        ] <=> [
            $this->analysisTimestamp($left)->getTimestamp(),
            $left->id,
        ]);

        return array_map(
            fn (DigestItem $item): array => [
                'id' => $item->id,
                'source_key' => $item->source_key,
                'confidence' => $this->confidence($item) ?? 0.0,
                'content_type' => $this->contentType($item),
                'title' => $item->title,
                'limitations' => $this->limitations($item),
            ],
            array_slice($filtered, 0, $limit),
        );
    }

    private function analysisTimestamp(DigestItem $item): CarbonInterface
    {
        return $item->analyzed_at
            ?? $item->updated_at
            ?? $item->created_at
            ?? CarbonImmutable::createFromTimestamp(0);
    }

    private function contentType(DigestItem $item): ?string
    {
        $contentType = $this->classificationValue($item, 'content_type');

        if (! is_string($contentType) || trim($contentType) === '') {
            return null;
        }

        return trim($contentType);
    }

    private function confidence(DigestItem $item): ?float
    {
        $confidence = $this->classificationValue($item, 'confidence');

        if (is_int($confidence) || is_float($confidence)) {
            return max(0.0, min(1.0, (float) $confidence));
        }

        return null;
    }

    private function importance(DigestItem $item): ?int
    {
        $importance = $this->classificationValue($item, 'importance');

        if (is_int($importance)) {
            return $importance;
        }

        return null;
    }

    private function limitations(DigestItem $item): ?string
    {
        $analysisJson = $item->analysis_json;

        if (! is_array($analysisJson)) {
            return null;
        }

        $content = $analysisJson['content'] ?? null;

        if (! is_array($content)) {
            return null;
        }

        $limitations = $content['limitations'] ?? null;

        if (! is_string($limitations) || trim($limitations) === '') {
            return null;
        }

        return trim($limitations);
    }

    private function classificationValue(DigestItem $item, string $field): mixed
    {
        $analysisJson = $item->analysis_json;

        if (! is_array($analysisJson)) {
            return null;
        }

        $classification = $analysisJson['classification'] ?? null;

        if (! is_array($classification)) {
            return null;
        }

        return $classification[$field] ?? null;
    }

    private function confidenceBucket(float $confidence): string
    {
        if ($confidence <= 0.2) {
            return '0.0 - 0.2';
        }

        if ($confidence <= 0.5) {
            return '0.3 - 0.5';
        }

        if ($confidence <= 0.8) {
            return '0.6 - 0.8';
        }

        return '0.9 - 1.0';
    }

    /**
     * @param array<int|string, int> $buckets
     *
     * @return list<BucketRow>
     */
    private function bucketRows(array $buckets): array
    {
        $rows = [];

        foreach ($buckets as $label => $count) {
            $rows[] = [
                'label' => (string) $label,
                'count' => $count,
            ];
        }

        return $rows;
    }
}
