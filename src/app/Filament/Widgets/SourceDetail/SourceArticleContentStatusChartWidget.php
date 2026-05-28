<?php

namespace App\Filament\Widgets\SourceDetail;

use Filament\Widgets\ChartWidget;

/**
 * Source-specific article content status distribution を表示する widget
 */
class SourceArticleContentStatusChartWidget extends ChartWidget
{
    use SourceDetailWidget;

    protected ?string $heading = 'Article content status';

    protected ?string $description = 'Last 7 days';

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $rows = $this->sourceReport()['article_content_statuses'];

        return [
            'datasets' => [
                [
                    'label' => 'Digest Items',
                    'data' => array_column($rows, 'count'),
                    'backgroundColor' => '#0ea5e9',
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
