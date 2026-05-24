<?php

namespace App\Console\Commands;

use App\Jobs\SummarizeNewsItemJob;
use App\Jobs\TranslateNewsItemJob;
use App\Models\NewsItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * 未処理news itemの翻訳・要約jobをqueueへ投入するbatch commandです。
 */
class EnqueueProcessingCommand extends Command
{
    protected $signature = 'digestpipe:items:enqueue-processing
        {--limit= : Maximum news items to enqueue}
        {--dry-run : Inspect candidate items without dispatching jobs or changing statuses}
        {--only= : Enqueue only one processing type: translation or summary}';

    protected $description = 'Enqueue translation and summary jobs for fetched news items.';

    /**
     * 未処理news itemを検索し、必要なprocessing jobをdispatchします。
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->limitOption();
        $mode = $this->modeOption();

        Log::info('News item processing enqueue command started.', [
            'dry_run' => $dryRun,
            'mode' => $mode,
            'limit' => $limit,
        ]);

        $remaining = $limit;
        $translationCandidates = [];
        $summaryCandidates = [];

        if ($mode === 'translation' || $mode === 'both') {
            $translationCandidates = $this->translationCandidates($remaining);
            $remaining = $this->remainingLimit($remaining, count($translationCandidates));
        }

        if (($mode === 'summary' || $mode === 'both') && $remaining !== 0) {
            $summaryCandidates = $this->summaryCandidates($remaining);
        }

        $queuedTranslationCount = $this->enqueueTranslationJobs($translationCandidates, $dryRun);
        $queuedSummaryCount = $this->enqueueSummaryJobs($summaryCandidates, $dryRun);
        $candidateCount = count($translationCandidates) + count($summaryCandidates);
        $queuedJobCount = $queuedTranslationCount + $queuedSummaryCount;
        $skippedItemCount = $candidateCount - $queuedJobCount;

        Log::info('News item processing enqueue command finished.', [
            'dry_run' => $dryRun,
            'mode' => $mode,
            'limit' => $limit,
            'candidate_item_count' => $candidateCount,
            'queued_translation_job_count' => $queuedTranslationCount,
            'queued_summary_job_count' => $queuedSummaryCount,
            'queued_job_count' => $queuedJobCount,
            'skipped_item_count' => $skippedItemCount,
        ]);

        $this->info(sprintf(
            'Processing enqueue finished. Candidates: %d, queued: %d, translation: %d, summary: %d.',
            $candidateCount,
            $queuedJobCount,
            $queuedTranslationCount,
            $queuedSummaryCount,
        ));

        return self::SUCCESS;
    }

    /**
     * @return list<NewsItem>
     */
    private function translationCandidates(?int $limit): array
    {
        /** @var list<NewsItem> $items */
        $items = NewsItem::query()
            ->where('translation_status', 'pending')
            ->get()
            ->all();

        usort($items, static fn (NewsItem $left, NewsItem $right): int => $left->id <=> $right->id);

        if ($limit !== null) {
            $items = array_slice($items, 0, $limit);
        }

        Log::info('News item translation candidates resolved.', [
            'candidate_item_count' => count($items),
        ]);

        return $items;
    }

    /**
     * @return list<NewsItem>
     */
    private function summaryCandidates(?int $limit): array
    {
        /** @var list<NewsItem> $items */
        $items = NewsItem::query()
            ->where('translation_status', 'completed')
            ->where('summary_status', 'pending')
            ->get()
            ->all();

        usort($items, static fn (NewsItem $left, NewsItem $right): int => $left->id <=> $right->id);

        if ($limit !== null) {
            $items = array_slice($items, 0, $limit);
        }

        Log::info('News item summary candidates resolved.', [
            'candidate_item_count' => count($items),
        ]);

        return $items;
    }

    /**
     * @param list<NewsItem> $items
     */
    private function enqueueTranslationJobs(array $items, bool $dryRun): int
    {
        foreach ($items as $item) {
            Log::info('News item translation job selected.', [
                'news_item_id' => $item->id,
                'dry_run' => $dryRun,
            ]);

            if ($dryRun) {
                continue;
            }

            $item->forceFill([
                'translation_status' => 'queued',
                'processing_error' => null,
            ])->save();

            TranslateNewsItemJob::dispatch($item->id);

            Log::info('News item translation job queued.', [
                'news_item_id' => $item->id,
            ]);
        }

        return $dryRun ? 0 : count($items);
    }

    /**
     * @param list<NewsItem> $items
     */
    private function enqueueSummaryJobs(array $items, bool $dryRun): int
    {
        foreach ($items as $item) {
            Log::info('News item summary job selected.', [
                'news_item_id' => $item->id,
                'dry_run' => $dryRun,
            ]);

            if ($dryRun) {
                continue;
            }

            $item->forceFill([
                'summary_status' => 'queued',
                'processing_error' => null,
            ])->save();

            SummarizeNewsItemJob::dispatch($item->id);

            Log::info('News item summary job queued.', [
                'news_item_id' => $item->id,
            ]);
        }

        return $dryRun ? 0 : count($items);
    }

    private function remainingLimit(?int $limit, int $used): ?int
    {
        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $used);
    }

    private function modeOption(): string
    {
        $value = $this->option('only');

        if ($value === null || $value === '') {
            return 'both';
        }

        if ($value !== 'translation' && $value !== 'summary') {
            throw new InvalidArgumentException('The --only option must be translation or summary.');
        }

        return $value;
    }

    private function limitOption(): ?int
    {
        $value = $this->option('limit');

        if ($value === null || $value === '') {
            return $this->configuredBatchLimit();
        }

        $limit = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (! is_int($limit)) {
            throw new InvalidArgumentException('The --limit option must be a positive integer.');
        }

        return $limit;
    }

    private function configuredBatchLimit(): ?int
    {
        $value = config('digestpipe.ai.batch_limit');

        if (! is_int($value) || $value < 1) {
            return null;
        }

        return $value;
    }
}
