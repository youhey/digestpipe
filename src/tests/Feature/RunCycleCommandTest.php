<?php

namespace Tests\Feature;

use App\Models\FeedSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

/**
 * @internal
 */
class RunCycleCommandTest extends TestCase
{
    use RefreshDatabase;

    public function testRunCycleCommandCanRunWithEmptyQueue(): void
    {
        FeedSource::query()->delete();

        $command = $this->artisan('digestpipe:run-cycle', [
            '--feed-limit' => 1,
            '--item-limit' => 1,
            '--max-seconds' => 1,
        ]);

        assert($command instanceof PendingCommand);
        $command
            ->expectsOutput('Starting digestpipe run cycle.')
            ->expectsOutput('Finished digestpipe run cycle.')
            ->assertSuccessful();
    }

    public function testRunCycleRejectsInvalidMaxSeconds(): void
    {
        $command = $this->artisan('digestpipe:run-cycle', [
            '--max-seconds' => 0,
        ]);

        assert($command instanceof PendingCommand);
        $command->assertExitCode(2);
    }
}
