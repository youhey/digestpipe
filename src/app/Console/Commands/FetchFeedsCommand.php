<?php

namespace App\Console\Commands;

use App\Feeds\DigestItemIngestor;
use App\Feeds\FeedFetcher;
use App\Feeds\FeedSourceRepository;
use App\Feeds\RssFeedParser;
use App\Models\DigestpipeCommandRun;
use App\Support\DigestpipeCommandRunRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * RSS 情報源から Feed Item を取得して未登録の Digest Item を永続化
 */
class FetchFeedsCommand extends Command
{
    protected $signature = 'digestpipe:feeds:fetch
        {--source= : Fetch only one configured source key}
        {--dry-run : Fetch and parse feeds without writing records}
        {--limit= : Maximum feed items to process per source}';

    protected $description = 'Fetch configured RSS feeds and store new digest items.';

    private readonly FeedSourceRepository $sources;

    private readonly FeedFetcher $fetcher;

    private readonly RssFeedParser $parser;

    private readonly DigestItemIngestor $ingestor;

    private readonly DigestpipeCommandRunRecorder $commandRuns;

    /**
     * Constructor
     */
    public function __construct(
        FeedSourceRepository $sources,
        FeedFetcher $fetcher,
        RssFeedParser $parser,
        DigestItemIngestor $ingestor,
        DigestpipeCommandRunRecorder $commandRuns
    ) {
        $this->sources = $sources;
        $this->fetcher = $fetcher;
        $this->parser = $parser;
        $this->ingestor = $ingestor;
        $this->commandRuns = $commandRuns;

        parent::__construct();
    }

    /**
     * 設定されている RSS 情報源から Feed Item を取得して DB に保存する
     *
     * @return int success=0 or failure=1 or invalid=2
     */
    public function handle(): int
    {
        $run = $this->commandRuns->start('digestpipe:feeds:fetch', $this->commandArguments());

        try {
            return $this->handleWithRun($run);
        } catch (Throwable $exception) {
            $this->commandRuns->fail($run, $exception);

            throw $exception;
        }
    }

    private function handleWithRun(DigestpipeCommandRun $run): int
    {
        $sourceKey = $this->stringOption('source');
        $dryRun = $this->option('dry-run');
        $limit = $this->limitOption();

        Log::info('RSS feed fetch command started.', [
            'source_filter' => $sourceKey,
            'dry_run' => $dryRun,
            'limit' => $limit,
        ]);

        try {
            $sources = $this->sources->enabledSources($sourceKey);
        } catch (InvalidArgumentException $exception) {
            Log::warning('RSS feed fetch command rejected invalid source filter.', [
                'source_filter' => $sourceKey,
                'message' => $exception->getMessage(),
            ]);

            $this->error($exception->getMessage());
            $this->commandRuns->complete($run, [
                'exit_code' => self::INVALID,
                'source' => $sourceKey,
                'dry_run' => $dryRun,
                'limit' => $limit,
                'error' => $exception->getMessage(),
            ]);

            return self::INVALID;
        }

        Log::info('RSS feed fetch command resolved configured feeds.', [
            'configured_feed_count' => count($sources),
        ]);

        $createdCount = 0;
        $skippedDuplicateCount = 0;
        $failedItemCount = 0;
        $failedFeedCount = 0;

        foreach ($sources as $source) {
            Log::info('RSS feed fetch started.', [
                'source_key' => $source->key,
                'source_name' => $source->name,
                'feed_url' => $source->url,
            ]);

            try {
                $fetchedFeed = $this->fetcher->fetch($source);

                Log::info('RSS feed HTTP fetch finished.', [
                    'source_key' => $source->key,
                    'feed_url' => $source->url,
                    'http_status' => $fetchedFeed->statusCode,
                ]);

                if (! $fetchedFeed->successful) {
                    ++$failedFeedCount;

                    Log::warning('RSS feed fetch returned unsuccessful HTTP status.', [
                        'source_key' => $source->key,
                        'feed_url' => $source->url,
                        'http_status' => $fetchedFeed->statusCode,
                    ]);

                    continue;
                }

                $parsedFeed = $this->parser->parse($fetchedFeed->body, $limit);
                $result = $this->ingestor->ingest($source, $parsedFeed->items, $dryRun);

                $createdCount += $result->createdCount;
                $skippedDuplicateCount += $result->skippedDuplicateCount;
                $failedItemCount += $parsedFeed->failedItemCount;

                Log::info('RSS feed fetch finished.', [
                    'source_key' => $source->key,
                    'feed_url' => $source->url,
                    'parsed_item_count' => count($parsedFeed->items),
                    'created_item_count' => $result->createdCount,
                    'skipped_duplicate_count' => $result->skippedDuplicateCount,
                    'failed_item_count' => $parsedFeed->failedItemCount,
                    'dry_run' => $dryRun,
                ]);
            } catch (Throwable $exception) {
                ++$failedFeedCount;

                Log::error('RSS feed fetch failed with an unexpected exception.', [
                    'source_key' => $source->key,
                    'feed_url' => $source->url,
                    'exception_class' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        Log::info('RSS feed fetch command finished.', [
            'created_item_count' => $createdCount,
            'skipped_duplicate_count' => $skippedDuplicateCount,
            'failed_item_count' => $failedItemCount,
            'failed_feed_count' => $failedFeedCount,
            'dry_run' => $dryRun,
        ]);

        $this->info(sprintf(
            'RSS feed fetch finished. Created: %d, duplicates: %d, failed items: %d, failed feeds: %d.',
            $createdCount,
            $skippedDuplicateCount,
            $failedItemCount,
            $failedFeedCount,
        ));
        $this->commandRuns->complete($run, [
            'exit_code' => self::SUCCESS,
            'source' => $sourceKey,
            'dry_run' => $dryRun,
            'limit' => $limit,
            'configured_feeds' => count($sources),
            'created' => $createdCount,
            'duplicates' => $skippedDuplicateCount,
            'failed_items' => $failedItemCount,
            'failed_feeds' => $failedFeedCount,
        ]);

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function commandArguments(): array
    {
        return [
            'source' => $this->option('source'),
            'dry_run' => $this->option('dry-run'),
            'limit' => $this->option('limit'),
        ];
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

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
