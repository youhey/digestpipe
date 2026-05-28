<?php

namespace App\Filament\Resources\DigestItems\Pages;

use App\Filament\Resources\DigestItems\DigestItemResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Digest Item review 一覧画面
 */
class ListDigestItems extends ListRecords
{
    protected static string $resource = DigestItemResource::class;
}
