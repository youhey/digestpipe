<?php

namespace App\Filament\Widgets\SourceDetail;

use Filament\Widgets\Widget;

/**
 * Source-specific recent failed items を表示する widget
 */
class RecentFailedSourceItemsWidget extends Widget
{
    use SourceDetailWidget;

    protected string $view = 'filament.widgets.source-detail-table';

    protected array|int|string $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'heading' => 'Recent failed items',
            'description' => 'Latest 5 items in the last 7 days',
            'columns' => ['id', 'title', 'selection_score', 'selection_status', 'article_content_status', 'analysis_status', 'updated_at'],
            'rows' => $this->sourceReport()['recent']['failed'],
            'emptyMessage' => 'No recent failed items.',
        ];
    }
}
