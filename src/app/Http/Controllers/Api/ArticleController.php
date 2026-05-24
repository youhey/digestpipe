<?php

namespace App\Http\Controllers\Api;

use App\Digests\DigestExportItemBuilder;
use App\Http\Controllers\Controller;
use App\Models\NewsItem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 完了済みの記事分析結果を private API として返す
 */
class ArticleController extends Controller
{
    private const DEFAULT_LIMIT = 100;

    private const MAX_LIMIT = 500;

    private readonly DigestExportItemBuilder $builder;

    /**
     * Constructor
     *
     * @param DigestExportItemBuilder $builder
     */
    public function __construct(DigestExportItemBuilder $builder)
    {
        $this->builder = $builder;
    }

    /**
     * 完了済み記事分析結果の一覧を返す
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $parameters = $this->indexParameters($request);

        if ($parameters instanceof JsonResponse) {
            return $parameters;
        }

        [$from, $to, $source, $limit] = $parameters;

        $items = $this->filteredItems($from, $to, $source, $limit);
        $records = array_map(
            fn (NewsItem $item): array => $this->builder->build($item),
            $items,
        );

        return response()->json([
            'data' => $records,
            'meta' => [
                'count' => count($records),
                'limit' => $limit,
                'from' => $from->toJSON(),
                'to' => $to->toJSON(),
            ],
        ]);
    }

    /**
     * 完了済み記事分析結果を ID で1件返す
     *
     * @param int $id
     *
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $item = NewsItem::query()
            ->where('analysis_status', 'completed')
            ->find($id);

        if (! $item instanceof NewsItem || ! $item->hasCompletedAnalysis()) {
            abort(404);
        }

        return response()->json([
            'data' => $this->builder->build($item),
        ]);
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable, string|null, int}|JsonResponse
     */
    private function indexParameters(Request $request): array|JsonResponse
    {
        $now = CarbonImmutable::now();
        $from = $this->dateQuery($request, 'from') ?? $now->subDay();
        $to = $this->dateQuery($request, 'to') ?? $now;

        if ($from === false || $to === false) {
            return $this->validationError('The from and to parameters must be valid ISO 8601 timestamps.');
        }

        if ($from->greaterThan($to)) {
            return $this->validationError('The from parameter must be before or equal to the to parameter.');
        }

        $limit = $this->limitQuery($request);

        if ($limit === null) {
            return $this->validationError('The limit parameter must be an integer between 1 and 500.');
        }

        return [$from, $to, $this->stringQuery($request, 'source'), $limit];
    }

    /**
     * @return list<NewsItem>
     */
    private function filteredItems(CarbonImmutable $from, CarbonImmutable $to, ?string $source, int $limit): array
    {
        /** @var list<NewsItem> $items */
        $items = NewsItem::query()
            ->where('analysis_status', 'completed')
            ->get()
            ->all();

        $items = array_values(array_filter(
            $items,
            fn (NewsItem $item): bool => $this->matchesIndexFilters($item, $from, $to, $source)
        ));

        usort($items, fn (NewsItem $left, NewsItem $right): int => [
            $this->effectiveTimestamp($right)->getTimestamp(),
            $right->id,
        ] <=> [
            $this->effectiveTimestamp($left)->getTimestamp(),
            $left->id,
        ]);

        return array_slice($items, 0, $limit);
    }

    private function matchesIndexFilters(NewsItem $item, CarbonImmutable $from, CarbonImmutable $to, ?string $source): bool
    {
        if (! $item->hasCompletedAnalysis()) {
            return false;
        }

        if ($source !== null && $item->source_key !== $source) {
            return false;
        }

        $timestamp = $this->effectiveTimestamp($item);

        return $timestamp >= $from && $timestamp <= $to;
    }

    private function effectiveTimestamp(NewsItem $item): CarbonInterface
    {
        return $item->published_at ?? $item->fetched_at;
    }

    private function dateQuery(Request $request, string $name): CarbonImmutable|false|null
    {
        $value = $this->stringQuery($request, $name);

        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (InvalidFormatException) {
            return false;
        }
    }

    private function limitQuery(Request $request): ?int
    {
        $value = $request->query('limit');

        if ($value === null || $value === '') {
            return self::DEFAULT_LIMIT;
        }

        if (! is_string($value)) {
            return null;
        }

        $limit = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
                'max_range' => self::MAX_LIMIT,
            ],
        ]);

        return is_int($limit) ? $limit : null;
    }

    private function stringQuery(Request $request, string $name): ?string
    {
        $value = $request->query($name);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function validationError(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], 422);
    }
}
