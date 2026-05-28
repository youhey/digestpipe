<?php

namespace App\Filament\Resources\NegativeKeywords;

use App\Filament\Resources\NegativeKeywords\Pages\CreateNegativeKeyword;
use App\Filament\Resources\NegativeKeywords\Pages\EditNegativeKeyword;
use App\Filament\Resources\NegativeKeywords\Pages\ListNegativeKeywords;
use App\Filament\Resources\SelectionKeywords\SelectionKeywordResource;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Negative Selection Keyword を管理する Filament resource
 */
class NegativeKeywordResource extends SelectionKeywordResource
{
    protected static BackedEnum|string|null $navigationIcon = Heroicon::FaceFrown;

    protected static string|UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 40;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $modelLabel = 'Negative Keyword';

    protected static ?string $pluralModelLabel = 'Negative Keywords';

    protected static ?string $slug = 'negative-keywords';

    /**
     * この Resource が扱う Selection Keyword type を返します。
     */
    public static function keywordType(): string
    {
        return 'negative';
    }

    /**
     * Negative Keyword の score range を返します。
     *
     * @return array{min: int, max: int}
     */
    public static function scoreRange(): array
    {
        return [
            'min' => -100,
            'max' => -1,
        ];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListNegativeKeywords::route('/'),
            'create' => CreateNegativeKeyword::route('/create'),
            'edit' => EditNegativeKeyword::route('/{record}/edit'),
        ];
    }
}
