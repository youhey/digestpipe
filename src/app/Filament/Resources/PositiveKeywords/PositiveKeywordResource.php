<?php

namespace App\Filament\Resources\PositiveKeywords;

use App\Filament\Resources\PositiveKeywords\Pages\CreatePositiveKeyword;
use App\Filament\Resources\PositiveKeywords\Pages\EditPositiveKeyword;
use App\Filament\Resources\PositiveKeywords\Pages\ListPositiveKeywords;
use App\Filament\Resources\SelectionKeywords\SelectionKeywordResource;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Support\Icons\Heroicon;

/**
 * Positive Selection Keyword を管理する Filament resource
 */
class PositiveKeywordResource extends SelectionKeywordResource
{
    protected static BackedEnum|string|null $navigationIcon = Heroicon::MagnifyingGlassPlus;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $modelLabel = 'Positive Keyword';

    protected static ?string $pluralModelLabel = 'Positive Keywords';

    protected static ?string $slug = 'positive-keywords';

    /**
     * この Resource が扱う Selection Keyword type を返します。
     */
    public static function keywordType(): string
    {
        return 'positive';
    }

    /**
     * Positive Keyword の score range を返します。
     *
     * @return array{min: int, max: int}
     */
    public static function scoreRange(): array
    {
        return [
            'min' => 1,
            'max' => 100,
        ];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListPositiveKeywords::route('/'),
            'create' => CreatePositiveKeyword::route('/create'),
            'edit' => EditPositiveKeyword::route('/{record}/edit'),
        ];
    }
}
