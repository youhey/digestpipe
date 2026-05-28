<?php

namespace App\Filament\Resources\SelectionKeywords\Pages;

use App\Filament\Resources\SelectionKeywords\SelectionKeywordResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

/**
 * Selection Keyword 編集画面
 */
class EditSelectionKeyword extends EditRecord
{
    protected static string $resource = SelectionKeywordResource::class;

    /**
     * @return array<DeleteAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->successNotificationTitle('Selection Keyword を削除しました。')
                ->failureNotificationTitle('Selection Keyword を削除できませんでした。'),
        ];
    }

    /**
     * 保存成功時の通知 title を返します。
     */
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Selection Keyword を更新しました。';
    }

    /**
     * 入力エラー時に失敗通知を表示します。
     */
    protected function onValidationError(ValidationException $exception): void
    {
        Notification::make()
            ->danger()
            ->title('Selection Keyword を更新できませんでした。')
            ->send();
    }
}
