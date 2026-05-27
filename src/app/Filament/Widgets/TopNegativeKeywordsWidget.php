<?php

namespace App\Filament\Widgets;

use App\Admin\DashboardSelectionStatsQuery;
use Filament\Widgets\ChartWidget;

/**
 * Negative keyword の match count を表示する chart widget
 */
class TopNegativeKeywordsWidget extends ChartWidget
{
    protected ?string $heading = 'Top negative keywords';

    protected ?string $description = 'Last 7 days';

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $keywords = app(DashboardSelectionStatsQuery::class)->report()['keywords']['negative'];

        return [
            'datasets' => [
                [
                    'label' => 'matches',
                    'data' => array_map(static fn (array $keyword): int => $keyword['count'], $keywords),
                    'backgroundColor' => '#ef4444',
                ],
            ],
            'labels' => array_map(static fn (array $keyword): string => $keyword['keyword'], $keywords),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'scales' => [
                'x' => ['beginAtZero' => true],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
