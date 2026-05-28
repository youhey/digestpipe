<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AnalysisInsights\AnalysisConfidenceDistributionWidget;
use App\Filament\Widgets\AnalysisInsights\AnalysisContentTypeBreakdownWidget;
use App\Filament\Widgets\AnalysisInsights\AnalysisContentTypeBySourceWidget;
use App\Filament\Widgets\AnalysisInsights\AnalysisImportanceDistributionWidget;
use App\Filament\Widgets\AnalysisInsights\LowConfidenceAnalysisItemsWidget;
use App\Filament\Widgets\AnalysisInsights\RecentAnalysisSamplesWidget;
use App\Insights\AnalysisInsightsExporter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * AI analysis output の品質確認用 Filament page
 */
class AnalysisInsights extends Page
{
    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $navigationLabel = 'Analysis Insights';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

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

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportAnalysisInsights')
                ->label('Export Insights')
                ->icon(Heroicon::ArrowDownTray)
                ->form([
                    TextInput::make('days')
                        ->numeric()
                        ->minValue(1)
                        ->default(30)
                        ->required(),
                ])
                ->action(function (array $data): StreamedResponse {
                    $result = app(AnalysisInsightsExporter::class)->export(
                        $this->positiveIntegerValue($data['days'] ?? null, 30),
                    );

                    return response()->streamDownload(
                        static function () use ($result): void {
                            echo $result->content;
                        },
                        $result->filename,
                        ['Content-Type' => $result->mimeType],
                    );
                }),
        ];
    }

    private function positiveIntegerValue(mixed $value, int $default): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (! is_int($integer)) {
            return $default;
        }

        return $integer;
    }
}
