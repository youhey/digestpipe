<?php

namespace App\Filament\Resources\SelectionKeywords\Pages;

use App\Filament\Resources\SelectionKeywords\SelectionKeywordResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

/**
 * Selection Keyword 作成画面
 */
class CreateSelectionKeyword extends CreateRecord
{
    protected static string $resource = SelectionKeywordResource::class;

    /**
     * 作成成功時の通知 title を返します。
     */
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Selection Keyword を作成しました。';
    }

    /**
     * 入力エラー時に失敗通知を表示します。
     */
    protected function onValidationError(ValidationException $exception): void
    {
        Notification::make()
            ->danger()
            ->title('Selection Keyword を作成できませんでした。')
            ->send();
    }
}
