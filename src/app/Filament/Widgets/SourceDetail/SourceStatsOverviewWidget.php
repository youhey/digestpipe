<?php

namespace App\Filament\Widgets\SourceDetail;

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

        return [
            Stat::make('Total Digest Items', $kpis['total'])
                ->icon(Heroicon::RectangleStack),
            Stat::make('Selected', $kpis['selected'])
                ->color('success')
                ->icon(Heroicon::CheckCircle),
            Stat::make('Skipped', $kpis['skipped'])
                ->color('danger')
                ->icon(Heroicon::NoSymbol),
            Stat::make('Pending', $kpis['pending'])
                ->color('warning')
                ->icon(Heroicon::Clock),
            Stat::make('Content Failed', $kpis['content_failed'])
                ->color('danger')
                ->icon(Heroicon::ExclamationTriangle),
            Stat::make('Analysis Failed', $kpis['analysis_failed'])
                ->color('danger')
                ->icon(Heroicon::ExclamationTriangle),
            Stat::make('Avg Selection Score', $kpis['average_score'] ?? 'n/a')
                ->icon(Heroicon::ChartBar),
        ];
    }
}
