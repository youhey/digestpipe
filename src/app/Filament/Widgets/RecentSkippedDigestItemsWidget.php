<?php

namespace App\Filament\Widgets;

use App\Models\DigestItem;
use Carbon\CarbonImmutable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * 最近 skipped になった Digest Item を表示する table widget
 */
class RecentSkippedDigestItemsWidget extends TableWidget
{
    protected static ?string $heading = 'Recent skipped items';

    /**
     * @param Table $table
     *
     * @return Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->query($this->recentQuery('skipped'))
            ->defaultSort('selection_evaluated_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('source_key')
                    ->label('source')
                    ->sortable(),
                TextColumn::make('selection_score')
                    ->label('score')
                    ->sortable(),
                TextColumn::make('title')
                    ->limit(60)
                    ->searchable(),
                TextColumn::make('selection_reason')
                    ->label('reason')
                    ->limit(40),
            ])
            ->defaultPaginationPageOption(5)
            ->paginated([5]);
    }

    /**
     * @return Builder<DigestItem>
     */
    private function recentQuery(string $status): Builder
    {
        return DigestItem::query()
            ->where('selection_status', $status)
            ->where('selection_evaluated_at', '>=', CarbonImmutable::now()->subDays(7));
    }
}
