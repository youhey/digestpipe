<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AnalysisInsights\AnalysisConfidenceDistributionWidget;
use App\Filament\Widgets\AnalysisInsights\AnalysisContentTypeBreakdownWidget;
use App\Filament\Widgets\AnalysisInsights\AnalysisContentTypeBySourceWidget;
use App\Filament\Widgets\AnalysisInsights\AnalysisImportanceDistributionWidget;
use App\Filament\Widgets\AnalysisInsights\LowConfidenceAnalysisItemsWidget;
use App\Filament\Widgets\AnalysisInsights\RecentAnalysisSamplesWidget;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

/**
 * AI analysis output の品質確認用 Filament page
 */
class AnalysisInsights extends Page
{
    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $navigationLabel = 'Analysis Insights';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'analysis-insights';

    protected static ?string $title = 'Analysis Insights';

    protected ?string $subheading = 'Analysis JSON quality signals for the last 30 days. Content type values are shown exactly as stored.';

    /**
     * @return array<class-string<Widget>|WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            AnalysisContentTypeBreakdownWidget::class,
            AnalysisContentTypeBySourceWidget::class,
            AnalysisConfidenceDistributionWidget::class,
            AnalysisImportanceDistributionWidget::class,
            RecentAnalysisSamplesWidget::class,
            LowConfidenceAnalysisItemsWidget::class,
        ];
    }

    /**
     * @return int|array<string, ?int>
     */
    public function getColumns(): array|int
    {
        return 2;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getWidgetsContentComponent(),
            ]);
    }

    public function getWidgetsContentComponent(): Component
    {
        return Grid::make($this->getColumns())
            ->schema(fn (): array => $this->getWidgetsSchemaComponents($this->getWidgets()));
    }
}
