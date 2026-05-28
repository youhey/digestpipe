<?php

namespace Tests\Feature;

use App\Models\DigestpipeCommandRun;
use App\Support\DigestpipeCommandRunRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * @internal
 */
class DigestpipeCommandRunRecorderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function testCommandRunStartsWithStartedStatus(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-28T12:00:00Z'));

        $run = app(DigestpipeCommandRunRecorder::class)->start('digestpipe:feeds:fetch', [
            'source' => 'hacker_news',
        ]);

        self::assertSame('digestpipe:feeds:fetch', $run->command_name);
        self::assertSame('started', $run->status);
        self::assertSame(['source' => 'hacker_news'], $run->command_arguments);
        self::assertSame('2026-05-28T12:00:00.000000Z', $run->started_at->toJSON());
        self::assertNull($run->finished_at);
        self::assertNull($run->duration_ms);
    }

    public function testSuccessfulCommandCompletionRecordsSummaryAndDuration(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-28T12:00:00Z'));
        $recorder = app(DigestpipeCommandRunRecorder::class);
        $run = $recorder->start('digestpipe:items:enqueue-processing');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-28T12:00:01.250Z'));
        $recorder->complete($run, [
            'checked' => 10,
            'queued' => 3,
        ]);

        $run->refresh();

        self::assertSame('completed', $run->status);
        self::assertSame(1250, $run->duration_ms);
        self::assertSame(10, $run->result_summary['checked'] ?? null);
        self::assertSame(3, $run->result_summary['queued'] ?? null);
        self::assertNull($run->error_message);
    }

    public function testFailedCommandRecordsErrorMessageAndDuration(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-28T12:00:00Z'));
        $recorder = app(DigestpipeCommandRunRecorder::class);
        $run = $recorder->start('digestpipe:items:enqueue-processing');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-28T12:00:00.500Z'));
        $recorder->fail($run, new RuntimeException('Planner failed.'), [
            'checked' => 1,
        ]);

        $run->refresh();

        self::assertSame('failed', $run->status);
        self::assertSame(500, $run->duration_ms);
        self::assertSame(['checked' => 1], $run->result_summary);
        self::assertSame('Planner failed.', $run->error_message);
        self::assertDatabaseCount('digestpipe_command_runs', 1);
        self::assertInstanceOf(DigestpipeCommandRun::class, $run);
    }
}
