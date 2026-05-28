<?php

namespace App\Filament\Widgets\AnalysisInsights;

use App\Admin\AnalysisInsightsQuery;
use Filament\Widgets\Widget;

/**
 * source_key ごとの content_type 分布を表示する table widget
 */
class AnalysisContentTypeBySourceWidget extends Widget
{
    protected string $view = 'filament.widgets.analysis-insights-table';

    protected array|int|string $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'heading' => 'Content type by source',
            'description' => 'Last 30 days',
            'columns' => ['source_key', 'content_type', 'count'],
            'rows' => app(AnalysisInsightsQuery::class)->report()['content_types_by_source'],
            'emptyMessage' => 'No analyzed source content types.',
        ];
    }
}
