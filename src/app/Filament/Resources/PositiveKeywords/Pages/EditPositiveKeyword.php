<?php

namespace App\Filament\Resources\PositiveKeywords\Pages;

use App\Filament\Resources\PositiveKeywords\PositiveKeywordResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

/**
 * Positive Keyword 編集画面
 */
class EditPositiveKeyword extends EditRecord
{
    protected static string $resource = PositiveKeywordResource::class;

    /**
     * @return array<DeleteAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->successNotificationTitle('Positive Keyword を削除しました。')
                ->failureNotificationTitle('Positive Keyword を削除できませんでした。'),
        ];
    }

    /**
     * 更新データに positive type を設定します。
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['type'] = PositiveKeywordResource::keywordType();

        return $data;
    }

    /**
     * 保存成功時の通知 title を返します。
     */
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Positive Keyword を更新しました。';
    }

    /**
     * 入力エラー時に失敗通知を表示します。
     */
    protected function onValidationError(ValidationException $exception): void
    {
        Notification::make()
            ->danger()
            ->title('Positive Keyword を更新できませんでした。')
            ->send();
    }
}
