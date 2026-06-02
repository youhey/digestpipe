<?php

namespace App\Filament\Resources\FeedSources\Pages;

use App\Filament\Resources\FeedSources\FeedSourceResource;
use App\MasterData\MasterDataSpreadsheetService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Feed Source 一覧画面
 */
class ListFeedSources extends ListRecords
{
    protected static string $resource = FeedSourceResource::class;

    /**
     * @return array<int, Action|CreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Export Excel')
                ->icon(Heroicon::ArrowDownTray)
                ->action(static function (MasterDataSpreadsheetService $spreadsheets): StreamedResponse {
                    return response()->streamDownload(
                        static function () use ($spreadsheets): void {
                            echo $spreadsheets->exportFeedSources();
                        },
                        'feed-sources.xlsx',
                        ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                    );
                }),
            Action::make('importExcel')
                ->label('Import Excel')
                ->icon(Heroicon::ArrowUpTray)
                ->modalSubmitActionLabel('Import')
                ->form([
                    FileUpload::make('file')
                        ->label('Excel file')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->storeFiles(false)
                        ->required(),
                ])
                ->action(function (array $data, MasterDataSpreadsheetService $spreadsheets): void {
                    try {
                        $result = $spreadsheets->importFeedSources($this->uploadedPath($data['file'] ?? null));
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->danger()
                            ->title('Feed Sources を import できませんでした。')
                            ->body($exception->getMessage())
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title('Feed Sources を import しました。')
                        ->body($result->summary())
                        ->send();
                }),
            CreateAction::make()
                ->successNotificationTitle('Feed Source を作成しました。')
                ->failureNotificationTitle('Feed Source を作成できませんでした。'),
        ];
    }

    private function uploadedPath(mixed $file): string
    {
        if ($file instanceof TemporaryUploadedFile) {
            return $file->getRealPath();
        }

        abort(422, 'Excel file が不正です。');
    }
}
