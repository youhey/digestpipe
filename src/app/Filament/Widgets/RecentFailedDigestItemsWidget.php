<?php

namespace App\Filament\Widgets;

use App\Models\DigestItem;
use Carbon\CarbonImmutable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * 最近失敗した本文取得・分析 Digest Item を表示する table widget
 */
class RecentFailedDigestItemsWidget extends TableWidget
{
    protected static ?string $heading = 'Recent failed processing items';

    protected array|int|string $columnSpan = 'full';

    /**
     * @param Table $table
     *
     * @return Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->query($this->recentFailedQuery())
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('source_key')
                    ->label('source')
                    ->sortable(),
                TextColumn::make('title')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('failed_stage')
                    ->label('stage')
                    ->state(fn (DigestItem $record): string => $record->analysis_status === 'failed' ? 'analysis' : 'article_content'),
                TextColumn::make('failed_status')
                    ->label('status')
                    ->state(fn (DigestItem $record): string => $record->analysis_status === 'failed' ? $record->analysis_status : $record->article_content_status),
                TextColumn::make('error_summary')
                    ->label('error')
                    ->limit(80)
                    ->state(fn (DigestItem $record): ?string => $this->errorSummary($record)),
                TextColumn::make('updated_at')
                    ->dateTime('Y-m-d H:i:s T')
                    ->sortable(),
            ])
            ->defaultPaginationPageOption(5)
            ->paginated([5]);
    }

    /**
     * @return Builder<DigestItem>
     */
    private function recentFailedQuery(): Builder
    {
        return DigestItem::query()
            ->where('updated_at', '>=', CarbonImmutable::now()->subDays(7))
            ->where(static function (Builder $query): void {
                $query
                    ->where('article_content_status', 'failed')
                    ->orWhere('analysis_status', 'failed');
            });
    }

    private function errorSummary(DigestItem $record): ?string
    {
        $error = $record->analysis_status === 'failed'
            ? $record->analysis_error
            : $record->article_content_error;

        if (! is_string($error) || trim($error) === '') {
            return null;
        }

        return mb_substr(trim($error), 0, 160);
    }
}
