<?php

namespace App\Filament\Widgets\SourceDetail;

use Filament\Widgets\Widget;

/**
 * Source-specific content_type distribution を表示する widget
 */
class SourceContentTypeBreakdownWidget extends Widget
{
    use SourceDetailWidget;

    protected string $view = 'filament.widgets.analysis-insights-table';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'heading' => 'Content type breakdown',
            'description' => 'Last 7 days',
            'columns' => ['content_type', 'count'],
            'rows' => $this->sourceReport()['content_types'],
            'emptyMessage' => 'No analyzed content types.',
        ];
    }
}
