<?php

namespace App\Filament\Widgets\SourceDetail;

use Filament\Widgets\ChartWidget;

/**
 * Source-specific selection status distribution を表示する widget
 */
class SourceSelectionStatusChartWidget extends ChartWidget
{
    use SourceDetailWidget;

    protected ?string $heading = 'Source selection status';

    protected ?string $description = 'Last 7 days';

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $rows = $this->sourceReport()['selection_statuses'];

        return [
            'datasets' => [
                [
                    'data' => array_column($rows, 'count'),
                    'backgroundColor' => [
                        '#22c55e',
                        '#ef4444',
                        '#f59e0b',
                        '#6b7280',
                    ],
                ],
            ],
            'labels' => array_column($rows, 'status'),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
