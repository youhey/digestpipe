<?php

namespace App\Filament\Widgets\AnalysisInsights;

use App\Admin\AnalysisInsightsQuery;
use Filament\Widgets\Widget;

/**
 * content_type の分布を表示する table widget
 */
class AnalysisContentTypeBreakdownWidget extends Widget
{
    protected string $view = 'filament.widgets.analysis-insights-table';

    protected array|int|string $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'heading' => 'Content type breakdown',
            'description' => 'Last 30 days',
            'columns' => ['content_type', 'count'],
            'rows' => app(AnalysisInsightsQuery::class)->report()['content_types'],
            'emptyMessage' => 'No analyzed content types.',
        ];
    }
}
