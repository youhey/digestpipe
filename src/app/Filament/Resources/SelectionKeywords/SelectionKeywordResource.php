<?php

namespace App\Filament\Resources\SelectionKeywords;

use App\Filament\Resources\SelectionKeywords\Pages\CreateSelectionKeyword;
use App\Filament\Resources\SelectionKeywords\Pages\EditSelectionKeyword;
use App\Filament\Resources\SelectionKeywords\Pages\ListSelectionKeywords;
use App\Models\SelectionKeyword;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Selection Keyword master data を管理する Filament resource
 */
class SelectionKeywordResource extends Resource
{
    protected static ?string $model = SelectionKeyword::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::MagnifyingGlass;

    protected static ?string $modelLabel = 'Selection Keyword';

    protected static ?string $pluralModelLabel = 'Selection Keywords';

    protected static ?string $recordTitleAttribute = 'keyword';

    protected static ?string $slug = 'selection-keywords';

    /**
     * Selection Keyword の作成・編集 form を定義します。
     *
     * @param Schema $schema
     *
     * @return Schema
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('keyword')
                    ->required()
                    ->maxLength(255)
                    ->rule(static function (callable $get, ?SelectionKeyword $record): Unique {
                        $type = $get('type');

                        if (! is_string($type)) {
                            $type = '';
                        }

                        return Rule::unique('selection_keywords', 'keyword')
                            ->where('type', $type)
                            ->ignore($record?->id);
                    }),
                Select::make('type')
                    ->required()
                    ->options([
                        'positive' => 'positive',
                        'negative' => 'negative',
                    ]),
                Select::make('match_mode')
                    ->required()
                    ->default('contains')
                    ->helperText('contains は CJK や広い部分一致、word_boundary は短い英単語や略語、exact_phrase は語句や記号入り keyword に使います。')
                    ->options([
                        'contains' => 'contains',
                        'word_boundary' => 'word_boundary',
                        'exact_phrase' => 'exact_phrase',
                    ]),
                TextInput::make('score')
                    ->required()
                    ->integer()
                    ->rule('not_in:0'),
                Toggle::make('enabled')
                    ->required(),
                Select::make('locale')
                    ->required()
                    ->options([
                        'any' => 'any',
                        'en' => 'English',
                        'ja' => 'Japanese',
                    ]),
                TextInput::make('category')
                    ->maxLength(64)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/'),
                Textarea::make('notes')
                    ->maxLength(2000)
                    ->rows(4),
            ]);
    }

    /**
     * Selection Keyword 一覧 table を定義します。
     *
     * @param Table $table
     *
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('keyword')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->sortable(),
                TextColumn::make('match_mode')
                    ->sortable(),
                TextColumn::make('score')
                    ->sortable(),
                IconColumn::make('enabled')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('locale')
                    ->sortable(),
                TextColumn::make('category')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'positive' => 'positive',
                        'negative' => 'negative',
                    ]),
                SelectFilter::make('match_mode')
                    ->options([
                        'contains' => 'contains',
                        'word_boundary' => 'word_boundary',
                        'exact_phrase' => 'exact_phrase',
                    ]),
                SelectFilter::make('enabled')
                    ->options([
                        '1' => 'enabled',
                        '0' => 'disabled',
                    ]),
                SelectFilter::make('locale')
                    ->options([
                        'any' => 'any',
                        'en' => 'English',
                        'ja' => 'Japanese',
                    ]),
                SelectFilter::make('category')
                    ->options(fn (): array => self::categoryOptions()),
            ])
            ->recordActions([
                EditAction::make()
                    ->successNotificationTitle('Selection Keyword を更新しました。')
                    ->failureNotificationTitle('Selection Keyword を更新できませんでした。'),
                DeleteAction::make()
                    ->successNotificationTitle('Selection Keyword を削除しました。')
                    ->failureNotificationTitle('Selection Keyword を削除できませんでした。'),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListSelectionKeywords::route('/'),
            'create' => CreateSelectionKeyword::route('/create'),
            'edit' => EditSelectionKeyword::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function categoryOptions(): array
    {
        $options = [];

        foreach (DB::table('selection_keywords')->select('category')->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')->all() as $category) {
            if (is_string($category) && $category !== '') {
                $options[$category] = $category;
            }
        }

        return $options;
    }
}
