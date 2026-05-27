<?php

namespace App\Filament\Widgets;

use App\Admin\DashboardSelectionStatsQuery;
use Filament\Widgets\ChartWidget;

/**
 * Selection status の分布を表示する chart widget
 */
class SelectionStatusChartWidget extends ChartWidget
{
    protected ?string $heading = 'Selection status';

    protected ?string $description = 'Last 7 days';

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $summary = app(DashboardSelectionStatsQuery::class)->report()['summary'];

        return [
            'datasets' => [
                [
                    'data' => [
                        $summary['selected'],
                        $summary['skipped'],
                        $summary['pending'],
                        $summary['other'],
                    ],
                    'backgroundColor' => [
                        '#22c55e',
                        '#ef4444',
                        '#f59e0b',
                        '#6b7280',
                    ],
                ],
            ],
            'labels' => ['selected', 'skipped', 'pending', 'other'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
