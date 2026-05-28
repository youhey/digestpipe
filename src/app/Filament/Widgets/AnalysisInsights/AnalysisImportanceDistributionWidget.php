<?php

namespace App\Filament\Widgets\AnalysisInsights;

use App\Admin\AnalysisInsightsQuery;
use Filament\Widgets\ChartWidget;

/**
 * analysis importance の分布を表示する chart widget
 */
class AnalysisImportanceDistributionWidget extends ChartWidget
{
    protected ?string $heading = 'Importance distribution';

    protected ?string $description = 'Last 30 days';

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $rows = app(AnalysisInsightsQuery::class)->report()['importance_distribution'];

        return [
            'datasets' => [
                [
                    'label' => 'items',
                    'data' => array_column($rows, 'count'),
                    'backgroundColor' => '#a78bfa',
                ],
            ],
            'labels' => array_column($rows, 'label'),
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
