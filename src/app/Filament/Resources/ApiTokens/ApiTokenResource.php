<?php

namespace App\Filament\Resources\ApiTokens;

use App\ApiTokens\ApiTokenService;
use App\Filament\Resources\ApiTokens\Pages\ListApiTokens;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\PersonalAccessToken;
use UnitEnum;

/**
 * Web API token を管理する Filament resource
 */
class ApiTokenResource extends Resource
{
    protected static ?string $model = PersonalAccessToken::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::Key;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'API Token';

    protected static ?string $pluralModelLabel = 'API Tokens';

    protected static ?string $slug = 'api-tokens';

    /**
     * User に紐づく Sanctum token だけを一覧対象にします。
     *
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tokenable_type', User::class)
            ->with('tokenable');
    }

    /**
     * API token metadata の一覧 table を定義します。
     *
     * @param Table $table
     *
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user_name')
                    ->label('User name')
                    ->state(static fn (PersonalAccessToken $record): string => self::userName($record))
                    ->searchable(query: static function (Builder $query, string $search): Builder {
                        return self::searchTokenableUser($query, $search, 'name');
                    }),
                TextColumn::make('user_email')
                    ->label('User email')
                    ->state(static fn (PersonalAccessToken $record): string => self::userEmail($record))
                    ->searchable(query: static function (Builder $query, string $search): Builder {
                        return self::searchTokenableUser($query, $search, 'email');
                    }),
                TextColumn::make('name')
                    ->label('Token name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('abilities')
                    ->label('Abilities')
                    ->state(static fn (PersonalAccessToken $record): string => self::abilityList($record))
                    ->badge(),
                TextColumn::make('last_used_at')
                    ->label('Last used at')
                    ->dateTime('Y-m-d H:i:s T')
                    ->placeholder('N/A')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created at')
                    ->dateTime('Y-m-d H:i:s T')
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('Expires at')
                    ->dateTime('Y-m-d H:i:s T')
                    ->placeholder('N/A')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('revokeToken')
                    ->label('Revoke Token')
                    ->icon(Heroicon::Trash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('This immediately invalidates the selected API token.')
                    ->action(static function (PersonalAccessToken $record, ApiTokenService $tokens): void {
                        $tokens->revokeToken($record);

                        Notification::make()
                            ->success()
                            ->title('API token を失効しました。')
                            ->send();
                    }),
                Action::make('revokeAllUserTokens')
                    ->label('Revoke All For User')
                    ->icon(Heroicon::NoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('This immediately invalidates all API tokens for this user.')
                    ->visible(static fn (PersonalAccessToken $record): bool => $record->tokenable instanceof User)
                    ->action(static function (PersonalAccessToken $record, ApiTokenService $tokens): void {
                        if (! $record->tokenable instanceof User) {
                            return;
                        }

                        $count = $tokens->revokeAllTokens($record->tokenable);

                        Notification::make()
                            ->success()
                            ->title($count . ' API token を失効しました。')
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListApiTokens::route('/'),
        ];
    }

    private static function userName(PersonalAccessToken $record): string
    {
        if ($record->tokenable instanceof User) {
            return $record->tokenable->name;
        }

        return 'N/A';
    }

    private static function userEmail(PersonalAccessToken $record): string
    {
        if ($record->tokenable instanceof User) {
            return $record->tokenable->email;
        }

        return 'N/A';
    }

    private static function abilityList(PersonalAccessToken $record): string
    {
        $abilities = $record->abilities;

        if (! is_array($abilities) || $abilities === []) {
            return 'N/A';
        }

        $labels = [];

        foreach ($abilities as $ability) {
            if (is_scalar($ability)) {
                $labels[] = (string) $ability;
            }
        }

        if ($labels === []) {
            return 'N/A';
        }

        return implode(', ', $labels);
    }

    /**
     * @param Builder<PersonalAccessToken> $query
     *
     * @return Builder<PersonalAccessToken>
     */
    private static function searchTokenableUser(Builder $query, string $search, string $column): Builder
    {
        return $query->whereHasMorph(
            'tokenable',
            [User::class],
            static function (Builder $query) use ($search, $column): void {
                $query->where($column, 'like', '%' . $search . '%');
            },
        );
    }
}
