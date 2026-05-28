<?php

namespace Tests\Feature;

use App\Cloud\LaravelCloudDeploymentStatusQuery;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * @internal
 */
class LaravelCloudDeploymentStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function testUnconfiguredClientReturnsSafeEmptyState(): void
    {
        Http::fake();

        config([
            'services.laravel_cloud.api_token' => null,
            'services.laravel_cloud.environment_id' => null,
        ]);

        $status = app(LaravelCloudDeploymentStatusQuery::class)->status();

        self::assertFalse($status->configured);
        self::assertFalse($status->available);
        self::assertSame('not_configured', $status->status);
        Http::assertNothingSent();
    }

    public function testConfiguredClientCallsExpectedDeploymentEndpoint(): void
    {
        config([
            'services.laravel_cloud.api_token' => 'secret-token',
            'services.laravel_cloud.environment_id' => 'env-123',
        ]);

        Http::fake([
            'https://cloud.laravel.com/api/environments/env-123/deployments' => Http::response([
                'data' => [
                    [
                        'id' => 'deployment-1',
                        'status' => 'completed',
                        'branch' => 'main',
                        'commit_hash' => 'abc123',
                    ],
                ],
            ]),
        ]);

        $status = app(LaravelCloudDeploymentStatusQuery::class)->status();

        self::assertSame('completed', $status->status);
        self::assertSame('main', $status->branch);
        self::assertSame('abc123', $status->commitHash);
        self::assertStringNotContainsString('secret-token', serialize($status));

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://cloud.laravel.com/api/environments/env-123/deployments'
                && $request->hasHeader('Authorization', 'Bearer secret-token');
        });
    }

    public function testCompletedDeploymentIsMappedFromFinishedTimestamp(): void
    {
        $this->fakeDeployment([
            'id' => 'deployment-1',
            'branch_name' => 'main',
            'commit' => [
                'hash' => 'abc123',
                'message' => 'Deploy digestpipe',
                'author' => 'Example Author',
            ],
            'started_at' => '2026-05-28T01:00:00Z',
            'finished_at' => '2026-05-28T01:01:00Z',
        ]);

        $status = app(LaravelCloudDeploymentStatusQuery::class)->status();

        self::assertTrue($status->configured);
        self::assertTrue($status->available);
        self::assertSame('completed', $status->status);
        self::assertSame('main', $status->branch);
        self::assertSame('abc123', $status->commitHash);
        self::assertSame('Deploy digestpipe', $status->commitMessage);
        self::assertSame('Example Author', $status->commitAuthor);
        self::assertSame('2026-05-28T01:00:00Z', $status->startedAt);
        self::assertSame('2026-05-28T01:01:00Z', $status->finishedAt);
    }

    public function testDeploymentIsMappedFromJsonApiAttributesPayload(): void
    {
        $this->fakeDeployment([
            'id' => 'depl-123',
            'type' => 'deployments',
            'attributes' => [
                'status' => 'deployment.succeeded',
                'branch_name' => 'main',
                'commit_hash' => '1680e0ccfbce2edf75cb07ebf69e44dcca2e922c',
                'commit_message' => 'feat: add temporary digest item translation actions',
                'commit_author' => 'IKEDA Youhei',
                'failure_reason' => null,
                'started_at' => '2026-05-28T10:15:16.000000Z',
                'finished_at' => '2026-05-28T10:16:36.000000Z',
            ],
        ]);

        $status = app(LaravelCloudDeploymentStatusQuery::class)->status();

        self::assertSame('deployment.succeeded', $status->status);
        self::assertSame('depl-123', $status->deploymentId);
        self::assertSame('main', $status->branch);
        self::assertSame('1680e0ccfbce2edf75cb07ebf69e44dcca2e922c', $status->commitHash);
        self::assertSame('feat: add temporary digest item translation actions', $status->commitMessage);
        self::assertSame('IKEDA Youhei', $status->commitAuthor);
        self::assertSame('2026-05-28T10:15:16.000000Z', $status->startedAt);
        self::assertSame('2026-05-28T10:16:36.000000Z', $status->finishedAt);
        self::assertNull($status->failureReason);
    }

    public function testFailedDeploymentIsMappedFromFailureReason(): void
    {
        $this->fakeDeployment([
            'id' => 'deployment-1',
            'started_at' => '2026-05-28T01:00:00Z',
            'finished_at' => '2026-05-28T01:01:00Z',
            'failure_reason' => 'Build failed.',
        ]);

        $status = app(LaravelCloudDeploymentStatusQuery::class)->status();

        self::assertSame('failed', $status->status);
        self::assertSame('Build failed.', $status->failureReason);
    }

    public function testRunningDeploymentIsMappedFromStartedWithoutFinishedTimestamp(): void
    {
        $this->fakeDeployment([
            'id' => 'deployment-1',
            'started_at' => '2026-05-28T01:00:00Z',
            'finished_at' => null,
        ]);

        $status = app(LaravelCloudDeploymentStatusQuery::class)->status();

        self::assertSame('running', $status->status);
        self::assertSame('2026-05-28T01:00:00Z', $status->startedAt);
        self::assertNull($status->finishedAt);
    }

    public function testApiFailureReturnsSafeErrorState(): void
    {
        config([
            'services.laravel_cloud.api_token' => 'secret-token',
            'services.laravel_cloud.environment_id' => 'env-123',
        ]);

        Http::fake([
            'https://cloud.laravel.com/api/environments/env-123/deployments' => Http::response([
                'message' => 'Server error',
            ], 500),
        ]);

        $status = app(LaravelCloudDeploymentStatusQuery::class)->status();

        self::assertTrue($status->configured);
        self::assertFalse($status->available);
        self::assertSame('error', $status->status);
        self::assertSame('Laravel Cloud API request failed.', $status->errorMessage);
        self::assertStringNotContainsString('secret-token', serialize($status));
    }

    public function testDeploymentStatusIsCachedBriefly(): void
    {
        $this->fakeDeployment([
            'id' => 'deployment-1',
            'status' => 'completed',
        ]);

        $query = app(LaravelCloudDeploymentStatusQuery::class);

        self::assertSame('completed', $query->status()->status);
        self::assertSame('completed', $query->status()->status);
        Http::assertSentCount(1);
    }

    /**
     * @param array<string, mixed> $deployment
     */
    private function fakeDeployment(array $deployment): void
    {
        config([
            'services.laravel_cloud.api_token' => 'secret-token',
            'services.laravel_cloud.environment_id' => 'env-123',
        ]);

        Http::fake([
            'https://cloud.laravel.com/api/environments/env-123/deployments' => Http::response([
                'data' => [
                    $deployment,
                ],
            ]),
        ]);
    }
}
