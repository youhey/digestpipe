<?php

namespace App\Filament\Resources\FeedSources\Pages;

use App\Filament\Resources\FeedSources\FeedSourceResource;
use App\Filament\Widgets\SourceDetail\RecentFailedSourceItemsWidget;
use App\Filament\Widgets\SourceDetail\RecentSelectedSourceItemsWidget;
use App\Filament\Widgets\SourceDetail\RecentSkippedSourceItemsWidget;
use App\Filament\Widgets\SourceDetail\SourceAnalysisStatusChartWidget;
use App\Filament\Widgets\SourceDetail\SourceArticleContentStatusChartWidget;
use App\Filament\Widgets\SourceDetail\SourceContentTypeBreakdownWidget;
use App\Filament\Widgets\SourceDetail\SourceNegativeKeywordsWidget;
use App\Filament\Widgets\SourceDetail\SourcePositiveKeywordsWidget;
use App\Filament\Widgets\SourceDetail\SourceSelectionStatusChartWidget;
use App\Filament\Widgets\SourceDetail\SourceStatsOverviewWidget;
use App\Filament\Widgets\SourceDetail\SourceSummaryWidget;
use App\Models\FeedSource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

/**
 * Feed Source の operational detail を表示する read-only page
 */
class ViewFeedSource extends ViewRecord
{
    protected static string $resource = FeedSourceResource::class;

    /**
     * @return array<class-string<Widget>|WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            SourceSummaryWidget::class,
            SourceStatsOverviewWidget::class,
            SourceSelectionStatusChartWidget::class,
            SourceArticleContentStatusChartWidget::class,
            SourceAnalysisStatusChartWidget::class,
            SourcePositiveKeywordsWidget::class,
            SourceNegativeKeywordsWidget::class,
            SourceContentTypeBreakdownWidget::class,
            RecentSelectedSourceItemsWidget::class,
            RecentSkippedSourceItemsWidget::class,
            RecentFailedSourceItemsWidget::class,
        ];
    }

    /**
     * @return int|array<string, ?int>
     */
    public function getColumns(): array|int
    {
        return 2;
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        /** @var FeedSource $record */
        $record = $this->getRecord();

        return [
            'sourceKey' => $record->key,
        ];
    }

    public function getTitle(): string
    {
        /** @var FeedSource $record */
        $record = $this->getRecord();

        return 'Source detail: ' . $record->key;
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

    /**
     * @return array<EditAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
