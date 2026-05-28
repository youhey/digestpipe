<?php

namespace App\Filament\Resources\PositiveKeywords\Pages;

use App\Filament\Resources\PositiveKeywords\PositiveKeywordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * Positive Keyword 一覧画面
 */
class ListPositiveKeywords extends ListRecords
{
    protected static string $resource = PositiveKeywordResource::class;

    /**
     * @return array<int, CreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->successNotificationTitle('Positive Keyword を作成しました。')
                ->failureNotificationTitle('Positive Keyword を作成できませんでした。'),
        ];
    }
}
