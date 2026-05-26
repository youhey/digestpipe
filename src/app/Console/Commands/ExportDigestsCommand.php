<?php

namespace App\Console\Commands;

use App\Digests\DigestExportItemBuilder;
use App\Models\DigestItem;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;
use JsonException;

/**
 * 完了済み分析結果を構造化 digest JSON として標準出力へ export
 */
class ExportDigestsCommand extends Command
{
    protected $signature = 'digestpipe:digests:export
        {--limit=20 : Maximum digest records to export}
        {--source= : Export only one source key}
        {--topic= : Export only items with a matching analysis topic}
        {--content-type= : Export only items with a matching analysis content type}
        {--from= : Export items published on or after this date}
        {--to= : Export items published on or before this date}
        {--format=json : Export format: json or jsonl}';

    protected $description = 'Export completed analysis records as structured digest JSON.';

    private readonly DigestExportItemBuilder $builder;

    /**
     * Constructor
     *
     * @param DigestExportItemBuilder $builder
     */
    public function __construct(DigestExportItemBuilder $builder)
    {
        $this->builder = $builder;

        parent::__construct();
    }

    /**
     * 完了済み分析結果を指定 format で標準出力へ出力する
     *
     * @return int success=0 or failure=1 or invalid=2
     *
     * @throws JsonException
     */
    public function handle(): int
    {
        $format = $this->formatOption();

        if ($format === null) {
            $this->error('The --format option must be json or jsonl.');

            return self::INVALID;
        }

        $limit = $this->limitOption();

        if ($limit === null) {
            $this->error('The --limit option must be a positive integer.');

            return self::INVALID;
        }

        $from = $this->dateOption('from', false);
        $to = $this->dateOption('to', true);

        if ($from === false || $to === false) {
            $this->error('The --from and --to options must be valid dates.');

            return self::INVALID;
        }

        $records = array_map(
            fn (DigestItem $item): array => $this->builder->build($item),
            $this->items($limit, $from, $to),
        );

        if ($format === 'jsonl') {
            $this->writeJsonLines($records);

            return self::SUCCESS;
        }

        $this->line(json_encode([
            'data' => $records,
            'meta' => [
                'count' => count($records),
                'limit' => $limit,
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    /**
     * @return list<DigestItem>
     */
    private function items(int $limit, CarbonImmutable|false|null $from, CarbonImmutable|false|null $to): array
    {
        $source = $this->stringOption('source');
        $topic = $this->stringOption('topic');
        $contentType = $this->stringOption('content-type');

        /** @var list<DigestItem> $items */
        $items = DigestItem::query()
            ->where('analysis_status', 'completed')
            ->get()
            ->all();

        $filteredItems = array_values(array_filter(
            $items,
            fn (DigestItem $item): bool => $this->matchesFilters($item, $source, $topic, $contentType, $from, $to)
        ));

        usort($filteredItems, static fn (DigestItem $left, DigestItem $right): int => [
            $right->published_at?->getTimestamp() ?? 0,
            $right->id,
        ] <=> [
            $left->published_at?->getTimestamp() ?? 0,
            $left->id,
        ]);

        return array_slice($filteredItems, 0, $limit);
    }

    private function matchesFilters(DigestItem $item, ?string $source, ?string $topic, ?string $contentType, CarbonImmutable|false|null $from, CarbonImmutable|false|null $to): bool
    {
        if (! $item->hasCompletedAnalysis()) {
            return false;
        }

        if ($source !== null && $item->source_key !== $source) {
            return false;
        }

        if ($topic !== null && ! in_array($topic, $this->analysisTopics($item), true)) {
            return false;
        }

        if ($contentType !== null && $this->analysisContentType($item) !== $contentType) {
            return false;
        }

        if ($from instanceof CarbonImmutable && ($item->published_at === null || $item->published_at < $from)) {
            return false;
        }

        if ($to instanceof CarbonImmutable && ($item->published_at === null || $item->published_at > $to)) {
            return false;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function analysisTopics(DigestItem $item): array
    {
        $analysisJson = $item->analysis_json;

        if (! is_array($analysisJson)) {
            return [];
        }

        $classification = $analysisJson['classification'] ?? null;

        if (! is_array($classification)) {
            return [];
        }

        $topics = $classification['topics'] ?? null;

        if (! is_array($topics)) {
            return [];
        }

        return array_values(array_filter($topics, static fn (mixed $topic): bool => is_string($topic)));
    }

    private function analysisContentType(DigestItem $item): ?string
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

        return is_string($contentType) ? $contentType : null;
    }

    private function formatOption(): ?string
    {
        $format = $this->stringOption('format') ?? 'json';

        return in_array($format, ['json', 'jsonl'], true) ? $format : null;
    }

    private function limitOption(): ?int
    {
        $value = $this->option('limit');

        if ($value === null || $value === '') {
            return 20;
        }

        $limit = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return is_int($limit) ? $limit : null;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function dateOption(string $name, bool $endOfDay): CarbonImmutable|false|null
    {
        $value = $this->stringOption($name);

        if ($value === null) {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($value);
        } catch (InvalidFormatException) {
            return false;
        }

        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }

    /**
     * @param list<array<string, mixed>> $records
     *
     * @throws JsonException
     */
    private function writeJsonLines(array $records): void
    {
        foreach ($records as $record) {
            $this->line(json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
    }
}
