<?php

namespace App\Filament\Widgets;

use App\Admin\DashboardSelectionStatsQuery;
use Filament\Widgets\ChartWidget;

/**
 * Source ごとの selection 状態を表示する chart widget
 */
class SourceSelectionBreakdownWidget extends ChartWidget
{
    protected ?string $heading = 'Source breakdown';

    protected ?string $description = 'Last 7 days';

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $sources = app(DashboardSelectionStatsQuery::class)->report()['sources'];

        return [
            'datasets' => [
                [
                    'label' => 'selected',
                    'data' => array_map(static fn (array $source): int => $source['selected'], $sources),
                    'backgroundColor' => '#22c55e',
                ],
                [
                    'label' => 'skipped',
                    'data' => array_map(static fn (array $source): int => $source['skipped'], $sources),
                    'backgroundColor' => '#ef4444',
                ],
                [
                    'label' => 'pending',
                    'data' => array_map(static fn (array $source): int => $source['pending'], $sources),
                    'backgroundColor' => '#f59e0b',
                ],
                [
                    'label' => 'other',
                    'data' => array_map(static fn (array $source): int => $source['other'], $sources),
                    'backgroundColor' => '#6b7280',
                ],
            ],
            'labels' => array_map(static fn (array $source): string => $source['source_key'], $sources),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true, 'beginAtZero' => true],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
