<?php

namespace App\Filament\Resources\SelectionKeywords\Pages;

use App\Filament\Resources\SelectionKeywords\SelectionKeywordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * Selection Keyword 一覧画面
 */
class ListSelectionKeywords extends ListRecords
{
    protected static string $resource = SelectionKeywordResource::class;

    /**
     * @return array<int, CreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
