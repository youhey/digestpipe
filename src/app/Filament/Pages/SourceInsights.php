<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SourceInsightsTableWidget;
use App\Insights\SourceInsightsExporter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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
 * Feed Source を横断比較する read-only insights page
 */
class SourceInsights extends Page
{
    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?string $navigationLabel = 'Source Insights';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'source-insights';

    protected static ?string $title = 'Source Insights';

    protected ?string $subheading = 'Source value and pipeline health comparison for the last 7 days.';

    /**
     * @return array<class-string<Widget>|WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            SourceInsightsTableWidget::class,
        ];
    }

    /**
     * @return int|array<string, ?int>
     */
    public function getColumns(): array|int
    {
        return 1;
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
            Action::make('exportSourceInsights')
                ->label('Export Insights')
                ->icon(Heroicon::ArrowDownTray)
                ->form([
                    TextInput::make('days')
                        ->numeric()
                        ->minValue(1)
                        ->default(7)
                        ->required(),
                    Select::make('sort')
                        ->default('total')
                        ->options([
                            'total' => 'Total',
                            'selected-rate' => 'Selected Rate',
                            'skipped-rate' => 'Skipped Rate',
                            'pending-rate' => 'Pending Rate',
                            'analysis-completed-rate' => 'Analysis Completed Rate',
                            'failure-rate' => 'Failure Rate',
                            'average-score' => 'Average Selection Score',
                        ])
                        ->required(),
                ])
                ->action(function (array $data): StreamedResponse {
                    $result = app(SourceInsightsExporter::class)->export(
                        $this->positiveIntegerValue($data['days'] ?? null, 7),
                        $this->sortValue($data['sort'] ?? null),
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

    private function sortValue(mixed $value): string
    {
        $sort = is_string($value) ? $value : 'total';
        $allowed = [
            'total',
            'selected-rate',
            'skipped-rate',
            'pending-rate',
            'analysis-completed-rate',
            'failure-rate',
            'average-score',
        ];

        return in_array($sort, $allowed, true) ? $sort : 'total';
    }
}
