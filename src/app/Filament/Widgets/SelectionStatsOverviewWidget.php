<?php

namespace App\Filament\Widgets;

use App\Admin\DashboardSelectionStatsQuery;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Selection 状態の KPI を表示する dashboard widget
 */
class SelectionStatsOverviewWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Selection overview';

    protected ?string $description = 'Last 7 days';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $summary = app(DashboardSelectionStatsQuery::class)->report()['summary'];

        return [
            Stat::make('Total Digest Items', $summary['total'])
                ->icon(Heroicon::RectangleStack),
            Stat::make('Selected', $summary['selected'])
                ->color('success')
                ->icon(Heroicon::CheckCircle),
            Stat::make('Skipped', $summary['skipped'])
                ->color('danger')
                ->icon(Heroicon::NoSymbol),
            Stat::make('Pending', $summary['pending'])
                ->color('warning')
                ->icon(Heroicon::Clock),
            Stat::make('Other', $summary['other'])
                ->color('gray')
                ->icon(Heroicon::QuestionMarkCircle),
            Stat::make('Avg Selection Score', $summary['average_score'] ?? 'n/a')
                ->icon(Heroicon::ChartBar),
        ];
    }
}
