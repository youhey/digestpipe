<?php

namespace App\Filament\Resources\NegativeKeywords\Pages;

use App\Filament\Resources\NegativeKeywords\NegativeKeywordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * Negative Keyword 一覧画面
 */
class ListNegativeKeywords extends ListRecords
{
    protected static string $resource = NegativeKeywordResource::class;

    /**
     * @return array<int, CreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->successNotificationTitle('Negative Keyword を作成しました。')
                ->failureNotificationTitle('Negative Keyword を作成できませんでした。'),
        ];
    }
}
