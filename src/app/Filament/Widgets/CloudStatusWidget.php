<?php

namespace App\Filament\Widgets;

use App\Cloud\LaravelCloudDeploymentStatus;
use App\Cloud\LaravelCloudDeploymentStatusQuery;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * Laravel Cloud の deployment status を表示する dashboard widget
 */
class CloudStatusWidget extends Widget
{
    protected string $view = 'filament.widgets.cloud-status';

    protected array|int|string $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $status = app(LaravelCloudDeploymentStatusQuery::class)->status();

        return [
            'status' => $status,
            'rows' => $this->rows($status),
        ];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function rows(LaravelCloudDeploymentStatus $status): array
    {
        return [
            ['label' => 'Status', 'value' => $status->status],
            ['label' => 'Branch', 'value' => $this->value($status->branch)],
            ['label' => 'Commit', 'value' => $this->commitHash($status->commitHash)],
            ['label' => 'Commit message', 'value' => $this->value($status->commitMessage)],
            ['label' => 'Commit author', 'value' => $this->value($status->commitAuthor)],
            ['label' => 'Started at', 'value' => $this->timestamp($status->startedAt)],
            ['label' => 'Finished at', 'value' => $this->timestamp($status->finishedAt)],
            ['label' => 'Failure reason', 'value' => $this->value($status->failureReason)],
        ];
    }

    private function value(?string $value): string
    {
        return $value ?? 'N/A';
    }

    private function commitHash(?string $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        return strlen($value) > 12 ? substr($value, 0, 12) : $value;
    }

    private function timestamp(?string $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s T');
    }
}
