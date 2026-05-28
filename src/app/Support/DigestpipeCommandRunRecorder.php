<?php

namespace App\Support;

use App\Models\DigestpipeCommandRun;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * digestpipe Artisan command の開始・終了・失敗を記録します。
 */
class DigestpipeCommandRunRecorder
{
    /**
     * Command run を started として作成します。
     *
     * @param array<string, mixed> $arguments
     */
    public function start(string $commandName, array $arguments = []): DigestpipeCommandRun
    {
        return DigestpipeCommandRun::query()->create([
            'command_name' => $commandName,
            'command_arguments' => $arguments,
            'status' => 'started',
            'started_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * Command run を completed として終了します。
     *
     * @param array<string, mixed> $summary
     */
    public function complete(DigestpipeCommandRun $run, array $summary = []): void
    {
        $this->finish($run, 'completed', $summary, null);
    }

    /**
     * Command run を failed として終了します。
     *
     * @param array<string, mixed> $summary
     */
    public function fail(DigestpipeCommandRun $run, Throwable $exception, array $summary = []): void
    {
        $this->finish($run, 'failed', $summary, $this->safeErrorMessage($exception));
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function finish(DigestpipeCommandRun $run, string $status, array $summary, ?string $errorMessage): void
    {
        $finishedAt = CarbonImmutable::now();

        $run->forceFill([
            'status' => $status,
            'finished_at' => $finishedAt,
            'duration_ms' => max(0, (int) $run->started_at->diffInMilliseconds($finishedAt)),
            'result_summary' => $summary,
            'error_message' => $errorMessage,
        ])->save();
    }

    private function safeErrorMessage(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        if ($message === '') {
            return $exception::class;
        }

        return mb_substr($message, 0, 2000);
    }
}
