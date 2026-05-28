<?php

namespace App\Filament\Resources\NegativeKeywords\Pages;

use App\Filament\Resources\NegativeKeywords\NegativeKeywordResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

/**
 * Negative Keyword 作成画面
 */
class CreateNegativeKeyword extends CreateRecord
{
    protected static string $resource = NegativeKeywordResource::class;

    /**
     * 作成データに negative type を設定します。
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = NegativeKeywordResource::keywordType();

        return $data;
    }

    /**
     * 作成成功時の通知 title を返します。
     */
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Negative Keyword を作成しました。';
    }

    /**
     * 入力エラー時に失敗通知を表示します。
     */
    protected function onValidationError(ValidationException $exception): void
    {
        Notification::make()
            ->danger()
            ->title('Negative Keyword を作成できませんでした。')
            ->send();
    }
}
