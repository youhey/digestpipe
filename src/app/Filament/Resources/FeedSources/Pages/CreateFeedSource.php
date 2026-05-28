<?php

namespace App\Filament\Resources\FeedSources\Pages;

use App\Filament\Resources\FeedSources\FeedSourceResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

/**
 * Feed Source 作成画面
 */
class CreateFeedSource extends CreateRecord
{
    protected static string $resource = FeedSourceResource::class;

    /**
     * 作成成功時の通知 title を返します。
     */
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Feed Source を作成しました。';
    }

    /**
     * 入力エラー時に失敗通知を表示します。
     */
    protected function onValidationError(ValidationException $exception): void
    {
        Notification::make()
            ->danger()
            ->title('Feed Source を作成できませんでした。')
            ->send();
    }
}
