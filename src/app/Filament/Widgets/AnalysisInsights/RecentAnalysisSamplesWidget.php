<?php

namespace App\Filament\Widgets\AnalysisInsights;

use App\Admin\AnalysisInsightsQuery;
use Filament\Widgets\Widget;

/**
 * 最近の分析済み Digest Item sample を表示する table widget
 */
class RecentAnalysisSamplesWidget extends Widget
{
    protected string $view = 'filament.widgets.analysis-insights-table';

    protected array|int|string $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'heading' => 'Recent analysis samples',
            'description' => 'Latest 20 analyzed Digest Items from the last 30 days',
            'columns' => ['id', 'source_key', 'content_type', 'confidence', 'importance', 'title'],
            'rows' => app(AnalysisInsightsQuery::class)->report()['recent_samples'],
            'emptyMessage' => 'No recent analyzed Digest Items.',
        ];
    }
}
