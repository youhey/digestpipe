<?php

namespace App\Filament\Widgets\SourceDetail;

use App\Admin\SourceMetricsCalculator;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Source-specific KPI を表示する widget
 */
class SourceStatsOverviewWidget extends StatsOverviewWidget
{
    use SourceDetailWidget;

    protected ?string $heading = 'Source overview';

    protected ?string $description = 'Last 7 days';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $kpis = $this->sourceReport()['kpis'];
        $metrics = app(SourceMetricsCalculator::class);
        $total = $kpis['total'];

        return [
            Stat::make('Total Digest Items', $kpis['total'])
                ->color('gray')
                ->icon(Heroicon::RectangleStack),
            Stat::make('Selected', $metrics->countRate($kpis['selected'], $total))
                ->color('success')
                ->icon(Heroicon::CheckCircle),
            Stat::make('Skipped', $metrics->countRate($kpis['skipped'], $total))
                ->color('orange')
                ->icon(Heroicon::NoSymbol),
            Stat::make('Pending', $metrics->countRate($kpis['pending'], $total))
                ->color('warning')
                ->icon(Heroicon::Clock),
            Stat::make('Content Failed', $metrics->countRate($kpis['content_failed'], $total))
                ->color('danger')
                ->icon(Heroicon::ExclamationTriangle),
            Stat::make('Analysis Failed', $metrics->countRate($kpis['analysis_failed'], $total))
                ->color('danger')
                ->icon(Heroicon::ExclamationTriangle),
            Stat::make('Analysis Completed', $metrics->countRate($kpis['analysis_completed'], $total))
                ->color('success')
                ->icon(Heroicon::Sparkles),
            Stat::make('Avg Selection Score', $kpis['average_score'] ?? 'n/a')
                ->color('info')
                ->icon(Heroicon::ChartBar),
        ];
    }
}
