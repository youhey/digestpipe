<?php

namespace App\Filament\Widgets\SourceDetail;

use Filament\Widgets\Widget;

/**
 * Source-specific negative keyword signals を表示する widget
 */
class SourceNegativeKeywordsWidget extends Widget
{
    use SourceDetailWidget;

    protected string $view = 'filament.widgets.source-detail-table';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'heading' => 'Top negative keywords',
            'description' => 'Last 7 days',
            'columns' => ['keyword', 'count'],
            'rows' => $this->sourceReport()['keywords']['negative'],
            'emptyMessage' => 'No negative keyword matches.',
        ];
    }
}
