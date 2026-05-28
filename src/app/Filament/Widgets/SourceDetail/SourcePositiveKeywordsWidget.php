<?php

namespace App\Filament\Widgets\SourceDetail;

use Filament\Widgets\Widget;

/**
 * Source-specific positive keyword signals を表示する widget
 */
class SourcePositiveKeywordsWidget extends Widget
{
    use SourceDetailWidget;

    protected string $view = 'filament.widgets.source-detail-table';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'heading' => 'Top positive keywords',
            'description' => 'Last 7 days',
            'columns' => ['keyword', 'count'],
            'rows' => $this->sourceReport()['keywords']['positive'],
            'emptyMessage' => 'No positive keyword matches.',
        ];
    }
}
