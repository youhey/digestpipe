<?php

namespace App\Filament\Resources\FeedSources;

use App\Filament\Resources\FeedSources\Pages\CreateFeedSource;
use App\Filament\Resources\FeedSources\Pages\EditFeedSource;
use App\Filament\Resources\FeedSources\Pages\ListFeedSources;
use App\Filament\Resources\FeedSources\Pages\ViewFeedSource;
use App\Models\FeedSource;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

/**
 * Feed Source master data を管理する Filament resource
 */
class FeedSourceResource extends Resource
{
    protected static ?string $model = FeedSource::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::Rss;

    protected static ?string $modelLabel = 'Feed Source';

    protected static ?string $pluralModelLabel = 'Feed Sources';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'feed-sources';

    /**
     * Feed Source の作成・編集 form を定義します。
     *
     * @param Schema $schema
     *
     * @return Schema
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->required()
                    ->maxLength(64)
                    ->regex('/^[a-z0-9]+(?:_[a-z0-9]+)*$/')
                    ->unique(ignoreRecord: true)
                    ->readOnlyOn('edit'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('url')
                    ->required()
                    ->url()
                    ->maxLength(2048)
                    ->regex('/^https?:\/\//')
                    ->unique(ignoreRecord: true),
                Select::make('language')
                    ->required()
                    ->options([
                        'en' => 'English',
                        'ja' => 'Japanese',
                    ]),
                Toggle::make('enabled')
                    ->required()
                    ->live()
                    ->afterStateUpdated(static function (bool $state, Set $set): void {
                        if (! $state) {
                            $set('analysis_enabled', false);
                        }
                    }),
                Toggle::make('analysis_enabled')
                    ->required()
                    ->rule('prohibited_if:enabled,false'),
                Select::make('tier')
                    ->required()
                    ->options([
                        'core' => 'core',
                        'candidate' => 'candidate',
                    ]),
                TextInput::make('category')
                    ->required()
                    ->maxLength(64)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/'),
            ]);
    }

    /**
     * Feed Source 一覧 table を定義します。
     *
     * @param Table $table
     *
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('language')
                    ->sortable(),
                IconColumn::make('enabled')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('analysis_enabled')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('tier')
                    ->sortable(),
                TextColumn::make('category')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('enabled')
                    ->options([
                        '1' => 'enabled',
                        '0' => 'disabled',
                    ]),
                SelectFilter::make('analysis_enabled')
                    ->options([
                        '1' => 'enabled',
                        '0' => 'disabled',
                    ]),
                SelectFilter::make('language')
                    ->options([
                        'en' => 'English',
                        'ja' => 'Japanese',
                    ]),
                SelectFilter::make('tier')
                    ->options([
                        'core' => 'core',
                        'candidate' => 'candidate',
                    ]),
                SelectFilter::make('category')
                    ->options(fn (): array => self::categoryOptions()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->successNotificationTitle('Feed Source を更新しました。')
                    ->failureNotificationTitle('Feed Source を更新できませんでした。'),
                DeleteAction::make()
                    ->successNotificationTitle('Feed Source を削除しました。')
                    ->failureNotificationTitle('Feed Source を削除できませんでした。'),
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
            'index' => ListFeedSources::route('/'),
            'create' => CreateFeedSource::route('/create'),
            'view' => ViewFeedSource::route('/{record}'),
            'edit' => EditFeedSource::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function categoryOptions(): array
    {
        $options = [];

        foreach (DB::table('feed_sources')->select('category')->distinct()->orderBy('category')->pluck('category')->all() as $category) {
            if (is_string($category) && $category !== '') {
                $options[$category] = $category;
            }
        }

        return $options;
    }
}
