<?php

namespace App\Filament\Resources\DigestItems\Pages;

use App\Filament\Resources\DigestItems\DigestItemResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

/**
 * Digest Item review 詳細画面
 */
class ViewDigestItem extends ViewRecord
{
    protected static string $resource = DigestItemResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return DigestItemResource::manualRatingActions();
    }
}
