<?php

namespace App\Filament\Widgets\SourceDetail;

use Filament\Widgets\ChartWidget;

/**
 * Source-specific analysis status distribution を表示する widget
 */
class SourceAnalysisStatusChartWidget extends ChartWidget
{
    use SourceDetailWidget;

    protected ?string $heading = 'Analysis status';

    protected ?string $description = 'Last 7 days';

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $rows = $this->sourceReport()['analysis_statuses'];

        return [
            'datasets' => [
                [
                    'label' => 'Digest Items',
                    'data' => array_column($rows, 'count'),
                    'backgroundColor' => '#8b5cf6',
                ],
            ],
            'labels' => array_column($rows, 'status'),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
