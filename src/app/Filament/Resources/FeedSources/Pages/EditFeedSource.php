<?php

namespace App\Filament\Resources\FeedSources\Pages;

use App\Filament\Resources\FeedSources\FeedSourceResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

/**
 * Feed Source 編集画面
 */
class EditFeedSource extends EditRecord
{
    protected static string $resource = FeedSourceResource::class;

    /**
     * @return array<DeleteAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->successNotificationTitle('Feed Source を削除しました。')
                ->failureNotificationTitle('Feed Source を削除できませんでした。'),
        ];
    }

    /**
     * 保存成功時の通知 title を返します。
     */
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Feed Source を更新しました。';
    }

    /**
     * 入力エラー時に失敗通知を表示します。
     */
    protected function onValidationError(ValidationException $exception): void
    {
        Notification::make()
            ->danger()
            ->title('Feed Source を更新できませんでした。')
            ->send();
    }
}
