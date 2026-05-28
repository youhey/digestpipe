<?php

namespace App\Filament\Resources\FeedSources\Pages;

use App\Filament\Resources\FeedSources\FeedSourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * Feed Source 一覧画面
 */
class ListFeedSources extends ListRecords
{
    protected static string $resource = FeedSourceResource::class;

    /**
     * @return array<int, CreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->successNotificationTitle('Feed Source を作成しました。')
                ->failureNotificationTitle('Feed Source を作成できませんでした。'),
        ];
    }
}
