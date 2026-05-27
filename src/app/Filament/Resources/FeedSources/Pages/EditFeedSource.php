<?php

namespace App\Filament\Resources\FeedSources\Pages;

use App\Filament\Resources\FeedSources\FeedSourceResource;
use Filament\Resources\Pages\EditRecord;

/**
 * Feed Source 編集画面
 */
class EditFeedSource extends EditRecord
{
    protected static string $resource = FeedSourceResource::class;
}
