<?php

namespace App\Cloud;

/**
 * Laravel Cloud の最新 deployment 表示に使う状態を保持します。
 */
class LaravelCloudDeploymentStatus
{
    /** @var bool Laravel Cloud API 設定が揃っているか */
    public bool $configured;

    /** @var bool deployment data を表示できるか */
    public bool $available;

    /** @var string deployment status または safe state */
    public string $status;

    /** @var string|null deployment ID */
    public ?string $deploymentId;

    /** @var string|null branch name */
    public ?string $branch;

    /** @var string|null commit hash */
    public ?string $commitHash;

    /** @var string|null commit message */
    public ?string $commitMessage;

    /** @var string|null commit author */
    public ?string $commitAuthor;

    /** @var string|null deployment started timestamp */
    public ?string $startedAt;

    /** @var string|null deployment finished timestamp */
    public ?string $finishedAt;

    /** @var string|null deployment failure reason */
    public ?string $failureReason;

    /** @var string|null safe error message */
    public ?string $errorMessage;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->configured = true;
        $this->available = true;
        $this->status = 'unknown';
        $this->deploymentId = null;
        $this->branch = null;
        $this->commitHash = null;
        $this->commitMessage = null;
        $this->commitAuthor = null;
        $this->startedAt = null;
        $this->finishedAt = null;
        $this->failureReason = null;
        $this->errorMessage = null;
    }

    /**
     * Laravel Cloud API 設定がない状態を返します。
     */
    public static function notConfigured(): self
    {
        $status = new self();
        $status->configured = false;
        $status->available = false;
        $status->status = 'not_configured';

        return $status;
    }

    /**
     * API failure などで deployment data が取得できない状態を返します。
     */
    public static function error(string $message): self
    {
        $status = new self();
        $status->available = false;
        $status->status = 'error';
        $status->errorMessage = $message;

        return $status;
    }

    /**
     * deployment が存在しない状態を返します。
     */
    public static function empty(): self
    {
        $status = new self();
        $status->available = false;
        $status->status = 'unknown';
        $status->errorMessage = 'No deployments found.';

        return $status;
    }

    /**
     * Laravel Cloud API response の deployment payload から表示状態を生成します。
     *
     * @param array<string, mixed> $deployment
     */
    public static function fromDeployment(array $deployment): self
    {
        $status = new self();
        $commit = self::arrayValue($deployment['commit'] ?? null);

        $status->deploymentId = self::stringValue($deployment['id'] ?? null);
        $status->branch = self::firstStringValue($deployment, ['branch', 'branch_name']);
        $status->commitHash = self::firstStringValue($deployment, ['commit_hash', 'commit_sha', 'commit'])
            ?? self::firstStringValue($commit, ['hash', 'sha']);
        $status->commitMessage = self::firstStringValue($deployment, ['commit_message'])
            ?? self::firstStringValue($commit, ['message']);
        $status->commitAuthor = self::firstStringValue($deployment, ['commit_author', 'author'])
            ?? self::firstStringValue($commit, ['author', 'author_name']);
        $status->startedAt = self::firstStringValue($deployment, ['started_at']);
        $status->finishedAt = self::firstStringValue($deployment, ['finished_at']);
        $status->failureReason = self::firstStringValue($deployment, ['failure_reason']);
        $status->status = self::resolveStatus($deployment, $status->startedAt, $status->finishedAt, $status->failureReason);

        return $status;
    }

    /**
     * @param array<string, mixed> $deployment
     */
    private static function resolveStatus(array $deployment, ?string $startedAt, ?string $finishedAt, ?string $failureReason): string
    {
        $explicitStatus = self::stringValue($deployment['status'] ?? null);

        if ($explicitStatus !== null) {
            return strtolower($explicitStatus);
        }

        if ($failureReason !== null) {
            return 'failed';
        }

        if ($startedAt !== null && $finishedAt === null) {
            return 'running';
        }

        if ($finishedAt !== null) {
            return 'completed';
        }

        return 'unknown';
    }

    /**
     * @param array<string, mixed> $source
     * @param list<string> $keys
     */
    private static function firstStringValue(array $source, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = self::stringValue($source[$key] ?? null);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private static function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private static function arrayValue(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $value */
        return $value;
    }
}
