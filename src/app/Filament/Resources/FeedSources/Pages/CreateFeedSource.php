<?php

namespace App\Filament\Resources\FeedSources\Pages;

use App\Filament\Resources\FeedSources\FeedSourceResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Feed Source 作成画面
 */
class CreateFeedSource extends CreateRecord
{
    protected static string $resource = FeedSourceResource::class;
}
