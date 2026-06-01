<?php

namespace App\Filament\Resources\ApiTokens\Pages;

use App\ApiTokens\ApiTokenService;
use App\Filament\Resources\ApiTokens\ApiTokenResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * API token 一覧と発行操作を提供する画面
 */
class ListApiTokens extends ListRecords
{
    public ?string $newPlainTextToken = null;

    public ?string $newTokenName = null;

    public ?string $newTokenUserEmail = null;

    protected static string $resource = ApiTokenResource::class;

    /**
     * 一度だけ表示する token 領域と一覧 table を配置します。
     *
     * @param Schema $schema
     *
     * @return Schema
     */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.resources.api-tokens.created-token')
                    ->viewData(fn (): array => [
                        'plainTextToken' => $this->newPlainTextToken,
                        'tokenName' => $this->newTokenName,
                        'userEmail' => $this->newTokenUserEmail,
                    ]),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('createApiToken')
                ->label('Create API Token')
                ->icon(Heroicon::Key)
                ->modalSubmitActionLabel('Create')
                ->form([
                    Select::make('user_id')
                        ->label('User')
                        ->options(static fn (): array => self::userOptions())
                        ->searchable()
                        ->required(),
                    TextInput::make('token_name')
                        ->label('Token name')
                        ->default('digestpipe-api')
                        ->required()
                        ->maxLength(255),
                    CheckboxList::make('abilities')
                        ->label('Abilities')
                        ->options(static fn (ApiTokenService $tokens): array => $tokens->allowedAbilities())
                        ->default(static fn (ApiTokenService $tokens): array => $tokens->defaultAbilities())
                        ->required()
                        ->columns(1),
                ])
                ->action(function (array $data, ApiTokenService $tokens): void {
                    try {
                        $user = User::query()->findOrFail($this->userIdFromData($data));
                        $abilities = $this->validatedAbilities($data['abilities'] ?? [], $tokens);
                        $createdToken = $tokens->createToken($user, $this->tokenNameFromData($data), $abilities);
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()
                            ->danger()
                            ->title('API token を作成できませんでした。')
                            ->body($exception->getMessage())
                            ->send();

                        return;
                    }

                    $this->newPlainTextToken = $createdToken->plainTextToken;
                    $this->newTokenName = $createdToken->accessToken->name;
                    $this->newTokenUserEmail = $user->email;

                    Notification::make()
                        ->success()
                        ->title('API token を作成しました。')
                        ->body('Plain text token はこの画面で一度だけ表示されます。')
                        ->send();
                }),
            Action::make('revokeAllApiTokens')
                ->label('Revoke All API Tokens')
                ->icon(Heroicon::NoSymbol)
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('This immediately invalidates all API tokens for the selected user.')
                ->form([
                    Select::make('user_id')
                        ->label('User')
                        ->options(static fn (): array => self::userOptions())
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data, ApiTokenService $tokens): void {
                    $user = User::query()->findOrFail($this->userIdFromData($data));
                    $count = $tokens->revokeAllTokens($user);

                    Notification::make()
                        ->success()
                        ->title($count . ' API token を失効しました。')
                        ->send();
                }),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function userOptions(): array
    {
        $options = [];

        foreach (DB::table('users')
            ->orderBy('email')
            ->get(['id', 'name', 'email']) as $user) {
            $id = filter_var($user->id ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if (! is_int($id)) {
                continue;
            }

            $name = is_string($user->name ?? null) ? $user->name : '';
            $email = is_string($user->email ?? null) ? $user->email : '';
            $options[$id] = $name . ' <' . $email . '>';
        }

        return $options;
    }

    /**
     * @param array<mixed> $data
     */
    private function userIdFromData(array $data): int
    {
        $userId = filter_var($data['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (! is_int($userId)) {
            abort(422, 'User が不正です。');
        }

        return $userId;
    }

    /**
     * @param array<mixed> $data
     */
    private function tokenNameFromData(array $data): string
    {
        $value = $data['token_name'] ?? null;

        if (! is_string($value) || trim($value) === '') {
            abort(422, 'Token name が不正です。');
        }

        return trim($value);
    }

    /**
     * @return list<string>
     */
    private function validatedAbilities(mixed $value, ApiTokenService $tokens): array
    {
        $abilities = [];

        foreach (is_array($value) ? array_values($value) : [] as $ability) {
            if (is_string($ability)) {
                $abilities[] = $ability;
            }
        }

        return $tokens->normalizeAllowedAbilities($abilities);
    }
}
