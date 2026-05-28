<?php

namespace App\Filament\Resources\PositiveKeywords\Pages;

use App\Filament\Resources\PositiveKeywords\PositiveKeywordResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

/**
 * Positive Keyword 作成画面
 */
class CreatePositiveKeyword extends CreateRecord
{
    protected static string $resource = PositiveKeywordResource::class;

    /**
     * 作成データに positive type を設定します。
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = PositiveKeywordResource::keywordType();

        return $data;
    }

    /**
     * 作成成功時の通知 title を返します。
     */
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Positive Keyword を作成しました。';
    }

    /**
     * 入力エラー時に失敗通知を表示します。
     */
    protected function onValidationError(ValidationException $exception): void
    {
        Notification::make()
            ->danger()
            ->title('Positive Keyword を作成できませんでした。')
            ->send();
    }
}
