<?php

namespace App\Filament\Widgets;

use App\Admin\PipelineHealthStatsQuery;
use Filament\Widgets\ChartWidget;

/**
 * Analysis status の分布を表示する chart widget
 */
class AnalysisStatusChartWidget extends ChartWidget
{
    protected ?string $heading = 'Analysis status';

    protected ?string $description = 'Last 7 days';

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $statuses = app(PipelineHealthStatsQuery::class)->report()['analysis_statuses'];

        return [
            'datasets' => [
                [
                    'label' => 'items',
                    'data' => array_values($statuses),
                    'backgroundColor' => '#a78bfa',
                ],
            ],
            'labels' => array_keys($statuses),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
