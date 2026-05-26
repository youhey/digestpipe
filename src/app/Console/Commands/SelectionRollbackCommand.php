<?php

namespace App\Console\Commands;

use App\Models\DigestItem;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * selection だけで停止したDigest Itemを再評価可能な状態へ戻す
 */
class SelectionRollbackCommand extends Command
{
    protected $signature = 'digestpipe:selection:rollback
        {--source= : Source key to rollback}
        {--status= : Selection status to rollback}
        {--dry-run : Inspect matching records without updating them}';

    protected $description = 'Rollback skipped selection state for one source.';

    /**
     * selection state の安全な rollback を実行します。
     *
     * @return int success=0 or invalid=2
     */
    public function handle(): int
    {
        try {
            $source = $this->requiredStringOption('source');
            $status = $this->requiredStringOption('status');
            $this->validateStatus($status);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $dryRun = $this->option('dry-run');
        $matchingItems = $this->matchingItems($source, $status);
        $targetItems = $this->targetItems($matchingItems);
        $matchingCount = count($matchingItems);
        $targetCount = count($targetItems);
        $downstreamSkippedCount = $matchingCount - $targetCount;
        $sampleItems = $this->sampleItems($targetItems);

        $this->info('Selection rollback');
        $this->line('source: ' . $source);
        $this->line('status: ' . $status);
        $this->line('target records: ' . $targetCount);
        $this->line('skipped due to downstream processing: ' . $downstreamSkippedCount);

        if ($sampleItems !== []) {
            $this->table(['id', 'source_key', 'selection_score', 'title'], $sampleItems);
        }

        if ($dryRun) {
            $this->warn('DRY RUN: no records were updated.');

            return self::SUCCESS;
        }

        $updatedCount = 0;
        foreach ($targetItems as $item) {
            $item->forceFill([
                'selection_status' => 'pending',
                'selection_score' => null,
                'selection_reason' => null,
                'selection_result' => null,
                'selection_evaluated_at' => null,
                'updated_at' => CarbonImmutable::now(),
            ])->save();

            ++$updatedCount;
        }

        $this->info('updated records: ' . $updatedCount);

        return self::SUCCESS;
    }

    /**
     * @return list<DigestItem>
     */
    private function matchingItems(string $source, string $status): array
    {
        /** @var list<DigestItem> $items */
        $items = DigestItem::query()
            ->where('source_key', $source)
            ->where('selection_status', $status)
            ->get()
            ->all();

        usort($items, static fn (DigestItem $left, DigestItem $right): int => $left->id <=> $right->id);

        return $items;
    }

    /**
     * @param list<DigestItem> $items
     *
     * @return list<DigestItem>
     */
    private function targetItems(array $items): array
    {
        return array_values(array_filter(
            $items,
            static fn (DigestItem $item): bool => $item->article_content_status === 'pending'
                && $item->analysis_status === 'pending',
        ));
    }

    /**
     * @param list<DigestItem> $items
     *
     * @return list<array{id: int, source_key: string, selection_score: int|null, title: string}>
     */
    private function sampleItems(array $items): array
    {
        return array_map(
            static fn (DigestItem $item): array => [
                'id' => $item->id,
                'source_key' => $item->source_key,
                'selection_score' => $item->selection_score,
                'title' => $item->title,
            ],
            array_slice($items, 0, 10),
        );
    }

    private function requiredStringOption(string $name): string
    {
        $value = $this->option($name);

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("The --{$name} option is required.");
        }

        return trim($value);
    }

    private function validateStatus(string $status): void
    {
        if ($status !== 'skipped') {
            throw new InvalidArgumentException('Unsupported rollback status: ' . $status);
        }
    }
}
