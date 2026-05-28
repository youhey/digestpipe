<?php

namespace App\Filament\Resources\NegativeKeywords\Pages;

use App\Filament\Resources\NegativeKeywords\NegativeKeywordResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

/**
 * Negative Keyword 編集画面
 */
class EditNegativeKeyword extends EditRecord
{
    protected static string $resource = NegativeKeywordResource::class;

    /**
     * @return array<DeleteAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->successNotificationTitle('Negative Keyword を削除しました。')
                ->failureNotificationTitle('Negative Keyword を削除できませんでした。'),
        ];
    }

    /**
     * 更新データに negative type を設定します。
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['type'] = NegativeKeywordResource::keywordType();

        return $data;
    }

    /**
     * 保存成功時の通知 title を返します。
     */
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Negative Keyword を更新しました。';
    }

    /**
     * 入力エラー時に失敗通知を表示します。
     */
    protected function onValidationError(ValidationException $exception): void
    {
        Notification::make()
            ->danger()
            ->title('Negative Keyword を更新できませんでした。')
            ->send();
    }
}
