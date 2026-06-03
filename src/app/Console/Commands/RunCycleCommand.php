<?php

namespace App\Console\Commands;

use App\Models\DigestpipeCommandRun;
use App\Support\DigestpipeCommandRunRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Feed 取得と一時 Queue Worker 実行を1 cycle として処理します。
 */
class RunCycleCommand extends Command
{
    protected $signature = 'digestpipe:run-cycle
        {--feed-limit=20 : Maximum feed items to process per source in this cycle}
        {--item-limit=50 : Maximum newly created digest items to dispatch for article content fetching}
        {--max-seconds=600 : Maximum seconds for this cycle}';

    protected $description = 'Run one cost-optimized digestpipe processing cycle.';

    private readonly DigestpipeCommandRunRecorder $commandRuns;

    /**
     * Constructor
     */
    public function __construct(DigestpipeCommandRunRecorder $commandRuns)
    {
        $this->commandRuns = $commandRuns;

        parent::__construct();
    }

    /**
     * Feed fetch と stop-when-empty Queue Worker を順番に実行します。
     *
     * @return int
     */
    public function handle(): int
    {
        $run = $this->commandRuns->start('digestpipe:run-cycle', $this->commandArguments());

        try {
            return $this->handleWithRun($run);
        } catch (Throwable $exception) {
            $this->commandRuns->fail($run, $exception);

            throw $exception;
        }
    }

    private function handleWithRun(DigestpipeCommandRun $run): int
    {
        try {
            $feedLimit = $this->positiveIntOption('feed-limit');
            $itemLimit = $this->positiveIntOption('item-limit');
            $maxSeconds = $this->positiveIntOption('max-seconds');
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            $this->commandRuns->complete($run, [
                'exit_code' => self::INVALID,
                'error' => $exception->getMessage(),
            ]);

            return self::INVALID;
        }

        $startedAt = CarbonImmutable::now();
        $deadline = $startedAt->addSeconds($maxSeconds);

        Log::info('Digestpipe run cycle started.', [
            'feed_limit' => $feedLimit,
            'item_limit' => $itemLimit,
            'max_seconds' => $maxSeconds,
        ]);
        $this->info('Starting digestpipe run cycle.');

        $feedExitCode = $this->call('digestpipe:feeds:fetch', [
            '--limit' => $feedLimit,
            '--item-dispatch-limit' => $itemLimit,
        ]);

        $queueExitCode = null;
        $queueMaxTime = 0;

        if (CarbonImmutable::now()->lessThan($deadline)) {
            $queueMaxTime = max(1, min($maxSeconds, CarbonImmutable::now()->diffInSeconds($deadline)));
            $queueExitCode = $this->call('queue:work', [
                'connection' => 'database',
                '--stop-when-empty' => true,
                '--max-time' => $queueMaxTime,
                '--sleep' => 1,
                '--tries' => 3,
                '--timeout' => 120,
                '--backoff' => 30,
            ]);
        } else {
            $this->warn('Digestpipe run cycle deadline reached after feed fetch.');
        }

        $finishedAt = CarbonImmutable::now();
        $durationSeconds = $startedAt->diffInSeconds($finishedAt);
        $maxTimeReached = $durationSeconds >= $maxSeconds;
        $exitCode = $feedExitCode === self::SUCCESS && ($queueExitCode === null || $queueExitCode === self::SUCCESS)
            ? self::SUCCESS
            : self::FAILURE;

        Log::info('Digestpipe run cycle finished.', [
            'duration_seconds' => $durationSeconds,
            'feed_exit_code' => $feedExitCode,
            'queue_worker_exit_code' => $queueExitCode,
            'queue_worker_max_time' => $queueMaxTime,
            'max_time_reached' => $maxTimeReached,
            'exit_code' => $exitCode,
        ]);

        $this->info('Finished digestpipe run cycle.');
        $this->commandRuns->complete($run, [
            'exit_code' => $exitCode,
            'feed_limit' => $feedLimit,
            'item_limit' => $itemLimit,
            'max_seconds' => $maxSeconds,
            'duration_seconds' => $durationSeconds,
            'feed_exit_code' => $feedExitCode,
            'queue_worker_exit_code' => $queueExitCode,
            'queue_worker_max_time' => $queueMaxTime,
            'max_time_reached' => $maxTimeReached,
        ]);

        return $exitCode;
    }

    /**
     * @return array<string, mixed>
     */
    private function commandArguments(): array
    {
        return [
            'feed_limit' => $this->option('feed-limit'),
            'item_limit' => $this->option('item-limit'),
            'max_seconds' => $this->option('max-seconds'),
        ];
    }

    private function positiveIntOption(string $name): int
    {
        $value = $this->option($name);
        $intValue = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (! is_int($intValue)) {
            throw new InvalidArgumentException("The --{$name} option must be a positive integer.");
        }

        return $intValue;
    }
}
