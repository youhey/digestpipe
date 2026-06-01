<?php

namespace App\Filament\Resources\ApiTokens;

use App\ApiTokens\ApiTokenService;
use App\Filament\Resources\ApiTokens\Pages\ListApiTokens;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
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
                    ->state(static fn (PersonalAccessToken $record): HtmlString => self::abilityBadges($record))
                    ->html(),
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
                Action::make('editToken')
                    ->label('Edit Token')
                    ->icon(Heroicon::PencilSquare)
                    ->modalSubmitActionLabel('Save')
                    ->fillForm(static fn (PersonalAccessToken $record): array => [
                        'token_name' => $record->name,
                        'abilities' => self::abilityValues($record),
                    ])
                    ->form([
                        TextInput::make('token_name')
                            ->label('Token name')
                            ->required()
                            ->maxLength(255),
                        CheckboxList::make('abilities')
                            ->label('Abilities')
                            ->options(static fn (ApiTokenService $tokens): array => $tokens->allowedAbilities())
                            ->required()
                            ->columns(1),
                    ])
                    ->action(static function (PersonalAccessToken $record, array $data, ApiTokenService $tokens): void {
                        try {
                            $tokens->updateTokenMetadata(
                                $record,
                                self::tokenNameFromData($data),
                                self::abilitiesFromData($data),
                            );
                        } catch (InvalidArgumentException $exception) {
                            Notification::make()
                                ->danger()
                                ->title('API token を更新できませんでした。')
                                ->body($exception->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('API token updated.')
                            ->send();
                    }),
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

    private static function abilityBadges(PersonalAccessToken $record): HtmlString
    {
        $abilities = self::abilityValues($record);

        if ($abilities === []) {
            return new HtmlString('<span style="color: rgb(107 114 128);">N/A</span>');
        }

        $html = '<div style="display: flex; flex-wrap: wrap; gap: 0.25rem;">';

        foreach ($abilities as $ability) {
            $html .= '<span class="api-token-ability-badge" style="display: inline-flex; align-items: center; border-radius: 9999px; background: rgb(243 244 246); padding: 0.125rem 0.5rem; font-size: 0.75rem; font-weight: 500; line-height: 1.25rem; color: rgb(55 65 81);">'
                . e($ability)
                . '</span>';
        }

        $html .= '</div>';

        return new HtmlString($html);
    }

    /**
     * @return list<string>
     */
    private static function abilityValues(PersonalAccessToken $record): array
    {
        $abilities = $record->abilities;

        if (! is_array($abilities)) {
            return [];
        }

        $values = [];

        foreach ($abilities as $ability) {
            if (is_scalar($ability) && trim((string) $ability) !== '') {
                $values[] = trim((string) $ability);
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param array<mixed> $data
     */
    private static function tokenNameFromData(array $data): string
    {
        $value = $data['token_name'] ?? null;

        if (! is_string($value)) {
            return '';
        }

        return trim($value);
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    private static function abilitiesFromData(array $data): array
    {
        $abilities = $data['abilities'] ?? [];

        if (! is_array($abilities)) {
            return [];
        }

        $values = [];

        foreach ($abilities as $ability) {
            if (is_string($ability)) {
                $values[] = $ability;
            }
        }

        return $values;
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
