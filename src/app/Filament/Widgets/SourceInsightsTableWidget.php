<?php

namespace App\Filament\Widgets;

use App\Admin\SourceInsightsQuery;
use Filament\Widgets\Widget;

/**
 * Feed Source 比較 table を表示する widget
 */
class SourceInsightsTableWidget extends Widget
{
    protected string $view = 'filament.widgets.analysis-insights-table';

    protected array|int|string $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'heading' => 'Source comparison',
            'description' => 'Last 7 days',
            'columns' => [
                'source_key',
                'total',
                'selected_rate',
                'skipped_rate',
                'pending_rate',
                'analysis_completed_rate',
                'failure_rate',
                'average_selection_score',
            ],
            'columnLabels' => [
                'source_key' => 'Source',
                'total' => 'Total',
                'selected_rate' => 'Selected Rate',
                'skipped_rate' => 'Skipped Rate',
                'pending_rate' => 'Pending Rate',
                'analysis_completed_rate' => 'Analysis Completed Rate',
                'failure_rate' => 'Failure Rate',
                'average_selection_score' => 'Average Selection Score',
            ],
            'rows' => app(SourceInsightsQuery::class)->tableRows(),
            'emptyMessage' => 'No source insight rows.',
            'defaultPerPage' => 50,
            'perPageOptions' => [10, 25, 50, 100],
        ];
    }
}
