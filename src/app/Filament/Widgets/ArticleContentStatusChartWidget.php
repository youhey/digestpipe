<?php

namespace App\Filament\Widgets;

use App\Admin\PipelineHealthStatsQuery;
use Filament\Widgets\ChartWidget;

/**
 * Article content status の分布を表示する chart widget
 */
class ArticleContentStatusChartWidget extends ChartWidget
{
    protected ?string $heading = 'Article content status';

    protected ?string $description = 'Last 7 days';

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $statuses = app(PipelineHealthStatsQuery::class)->report()['content_statuses'];

        return [
            'datasets' => [
                [
                    'label' => 'items',
                    'data' => array_values($statuses),
                    'backgroundColor' => '#38bdf8',
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
