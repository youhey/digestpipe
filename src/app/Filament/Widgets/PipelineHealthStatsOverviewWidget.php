<?php

namespace App\Filament\Widgets;

use App\Admin\PipelineHealthStatsQuery;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Pipeline health の KPI を表示する dashboard widget
 */
class PipelineHealthStatsOverviewWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Pipeline health';

    protected ?string $description = 'Last 7 days';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $kpis = app(PipelineHealthStatsQuery::class)->report()['kpis'];

        return [
            Stat::make('Content failed', $kpis['content_failed'])
                ->color($kpis['content_failed'] > 0 ? 'danger' : 'success')
                ->icon(Heroicon::ExclamationTriangle),
            Stat::make('Analysis failed', $kpis['analysis_failed'])
                ->color($kpis['analysis_failed'] > 0 ? 'danger' : 'success')
                ->icon(Heroicon::ExclamationTriangle),
            Stat::make('Content queued / processing', $kpis['content_active'])
                ->color('warning')
                ->icon(Heroicon::ArrowPath),
            Stat::make('Analysis queued / processing', $kpis['analysis_active'])
                ->color('warning')
                ->icon(Heroicon::ArrowPath),
            Stat::make('Analysis completed', $kpis['analysis_completed'])
                ->color('success')
                ->icon(Heroicon::CheckCircle),
        ];
    }
}
