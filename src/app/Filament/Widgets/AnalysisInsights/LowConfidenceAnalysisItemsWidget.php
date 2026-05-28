<?php

namespace App\Filament\Widgets\AnalysisInsights;

use App\Admin\AnalysisInsightsQuery;
use Filament\Widgets\Widget;

/**
 * confidence が低い分析済み Digest Item を表示する table widget
 */
class LowConfidenceAnalysisItemsWidget extends Widget
{
    protected string $view = 'filament.widgets.analysis-insights-table';

    protected array|int|string $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'heading' => 'Low confidence items',
            'description' => 'confidence < 0.6, latest 20 from the last 30 days',
            'columns' => ['id', 'source_key', 'confidence', 'content_type', 'title', 'limitations'],
            'rows' => app(AnalysisInsightsQuery::class)->report()['low_confidence_items'],
            'emptyMessage' => 'No low-confidence analyzed Digest Items.',
        ];
    }
}
