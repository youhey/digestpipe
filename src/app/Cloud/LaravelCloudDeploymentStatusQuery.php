<?php

namespace App\Cloud;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Laravel Cloud API から最新 deployment status を取得します。
 */
class LaravelCloudDeploymentStatusQuery
{
    private const API_BASE_URL = 'https://cloud.laravel.com/api';

    private const CACHE_TTL_SECONDS = 60;

    /**
     * 最新 deployment status を返します。
     */
    public function status(): LaravelCloudDeploymentStatus
    {
        $apiToken = $this->configString('services.laravel_cloud.api_token');
        $environmentId = $this->configString('services.laravel_cloud.environment_id');

        if ($apiToken === null || $environmentId === null) {
            return LaravelCloudDeploymentStatus::notConfigured();
        }

        $cacheKey = 'digestpipe:laravel-cloud:deployment-status:' . sha1($environmentId);

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL_SECONDS,
            fn (): LaravelCloudDeploymentStatus => $this->fetch($apiToken, $environmentId)
        );
    }

    private function fetch(string $apiToken, string $environmentId): LaravelCloudDeploymentStatus
    {
        try {
            $response = Http::withToken($apiToken)
                ->acceptJson()
                ->timeout(10)
                ->get(self::API_BASE_URL . '/environments/' . rawurlencode($environmentId) . '/deployments')
                ->throw();
        } catch (RequestException) {
            return LaravelCloudDeploymentStatus::error('Laravel Cloud API request failed.');
        } catch (Throwable) {
            return LaravelCloudDeploymentStatus::error('Laravel Cloud deployment status could not be loaded.');
        }

        $deployments = $this->deploymentsFromResponse($response->json());

        if ($deployments === []) {
            return LaravelCloudDeploymentStatus::empty();
        }

        return LaravelCloudDeploymentStatus::fromDeployment($deployments[0]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function deploymentsFromResponse(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $candidate = $payload['data'] ?? $payload;

        if (! is_array($candidate)) {
            return [];
        }

        if (array_key_exists('id', $candidate)) {
            /** @var array<string, mixed> $candidate */
            return [$candidate];
        }

        $deployments = [];

        foreach ($candidate as $deployment) {
            if (is_array($deployment)) {
                /** @var array<string, mixed> $deployment */
                $deployments[] = $deployment;
            }
        }

        return $deployments;
    }

    private function configString(string $key): ?string
    {
        $value = config($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
