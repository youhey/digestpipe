<?php

namespace App\Console\Commands;

use App\Jobs\FetchNewsItemArticleContentJob;
use App\Models\NewsItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Article content fetch jobをqueueへ投入するbatch commandです。
 */
class EnqueueContentFetchCommand extends Command
{
    protected $signature = 'digestpipe:items:enqueue-content-fetch
        {--limit= : Maximum news items to enqueue}
        {--dry-run : Inspect candidate items without dispatching jobs or changing statuses}
        {--source= : Enqueue only one source key}';

    protected $description = 'Enqueue article content fetch jobs for fetched news items.';

    /**
     * 未取得article contentを持つnews itemをqueueへ投入します。
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->limitOption();
        $sourceKey = $this->sourceOption();

        Log::info('Article content fetch enqueue command started.', [
            'dry_run' => $dryRun,
            'source_filter' => $sourceKey,
            'limit' => $limit,
        ]);

        $items = $this->candidates($sourceKey, $limit);
        $queuedCount = 0;
        $skippedCount = 0;

        foreach ($items as $item) {
            if (! is_string($item->source_url) || trim($item->source_url) === '') {
                ++$skippedCount;

                if (! $dryRun) {
                    $item->forceFill([
                        'article_content_status' => 'skipped',
                        'article_content_error' => 'Article URL is not available.',
                    ])->save();
                }

                continue;
            }

            Log::info('Article content fetch job selected.', [
                'news_item_id' => $item->id,
                'article_url' => $item->source_url,
                'dry_run' => $dryRun,
            ]);

            if ($dryRun) {
                continue;
            }

            $item->forceFill([
                'article_content_status' => 'queued',
                'article_content_error' => null,
            ])->save();

            FetchNewsItemArticleContentJob::dispatch($item->id);
            ++$queuedCount;

            Log::info('Article content fetch job queued.', [
                'news_item_id' => $item->id,
                'article_url' => $item->source_url,
            ]);
        }

        Log::info('Article content fetch enqueue command finished.', [
            'dry_run' => $dryRun,
            'source_filter' => $sourceKey,
            'limit' => $limit,
            'candidate_item_count' => count($items),
            'queued_job_count' => $queuedCount,
            'skipped_item_count' => $dryRun ? count($items) : $skippedCount,
        ]);

        $this->info(sprintf(
            'Article content fetch enqueue finished. Candidates: %d, queued: %d, skipped: %d.',
            count($items),
            $queuedCount,
            $dryRun ? count($items) : $skippedCount,
        ));

        return self::SUCCESS;
    }

    /**
     * @return list<NewsItem>
     */
    private function candidates(?string $sourceKey, ?int $limit): array
    {
        $query = NewsItem::query()
            ->where('article_content_status', 'pending');

        if ($sourceKey !== null) {
            $query->where('source_key', $sourceKey);
        }

        /** @var list<NewsItem> $items */
        $items = $query->get()->all();

        usort($items, static fn (NewsItem $left, NewsItem $right): int => $left->id <=> $right->id);

        if ($limit !== null) {
            $items = array_slice($items, 0, $limit);
        }

        return $items;
    }

    private function sourceOption(): ?string
    {
        $value = $this->option('source');

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function limitOption(): ?int
    {
        $value = $this->option('limit');

        if ($value === null || $value === '') {
            return null;
        }

        $limit = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (! is_int($limit)) {
            throw new InvalidArgumentException('The --limit option must be a positive integer.');
        }

        return $limit;
    }
}
