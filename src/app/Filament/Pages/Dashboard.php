<?php

namespace App\Filament\Pages;

use App\Feeds\FeedSourceRepository;
use App\Insights\InsightsExportOptions;
use App\Insights\SelectionInsightsExporter;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * digestpipe admin dashboard
 */
class Dashboard extends \Filament\Pages\Dashboard
{
    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportInsights')
                ->label('Export Insights')
                ->icon(Heroicon::ArrowDownTray)
                ->form([
                    TextInput::make('days')
                        ->numeric()
                        ->minValue(1)
                        ->default(7)
                        ->required(),
                    Select::make('source')
                        ->options(fn (): array => $this->sourceOptions())
                        ->placeholder('All sources'),
                    TextInput::make('sampleLimit')
                        ->label('Sample limit')
                        ->numeric()
                        ->minValue(1)
                        ->default(20)
                        ->required(),
                    TextInput::make('keywordLimit')
                        ->label('Keyword limit')
                        ->numeric()
                        ->minValue(1)
                        ->default(20)
                        ->required(),
                ])
                ->action(function (array $data): StreamedResponse {
                    $result = app(SelectionInsightsExporter::class)->export(InsightsExportOptions::make(
                        days: $this->positiveIntegerValue($data['days'] ?? null, 7),
                        source: $this->sourceValue($data['source'] ?? null),
                        sampleLimit: $this->positiveIntegerValue($data['sampleLimit'] ?? null, 20),
                        keywordLimit: $this->positiveIntegerValue($data['keywordLimit'] ?? null, 20),
                    ));

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

    /**
     * @return array<string, string>
     */
    private function sourceOptions(): array
    {
        $options = [];

        foreach (app(FeedSourceRepository::class)->allSources() as $source) {
            $options[$source->key] = $source->key;
        }

        return $options;
    }

    private function sourceValue(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
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
