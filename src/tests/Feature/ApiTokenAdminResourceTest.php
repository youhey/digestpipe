<?php

namespace Tests\Feature;

use App\ApiTokens\ApiTokenService;
use App\Filament\Resources\ApiTokens\ApiTokenResource;
use App\Filament\Resources\ApiTokens\Pages\ListApiTokens;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * @internal
 */
class ApiTokenAdminResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['digestpipe.admin.allowed_emails' => ['admin@example.test']]);
    }

    public function testAuthorizedAdminCanAccessApiTokenManagementUi(): void
    {
        $this->actingAsAdmin();

        $this->get('/admin/api-tokens')
            ->assertOk()
            ->assertSee('API Tokens');
    }

    public function testUnauthorizedUserCannotAccessApiTokenManagementUi(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'other@example.test']))
            ->get('/admin/api-tokens')
            ->assertForbidden();
    }

    public function testApiTokenResourceUsesSettingsNavigationGroup(): void
    {
        self::assertSame('Settings', ApiTokenResource::getNavigationGroup());
    }

    public function testApiTokenServiceAllowsReadAndRateAbilities(): void
    {
        self::assertSame([
            'digests:read' => 'digests:read',
            'digests:rate' => 'digests:rate',
        ], app(ApiTokenService::class)->allowedAbilities());
        self::assertSame(['digests:read'], app(ApiTokenService::class)->defaultAbilities());
    }

    public function testCreateApiTokenActionCreatesTokenAndShowsPlainTextOnce(): void
    {
        $this->actingAsAdmin();
        $apiUser = User::factory()->create([
            'name' => 'API User',
            'email' => 'api@example.test',
        ]);

        /** @var Testable<ListApiTokens> $component */
        $component = Livewire::test(ListApiTokens::class);

        /** @phpstan-ignore-next-line Livewire の action test helper は runtime に追加されます。 */
        $component
            ->callAction('createApiToken', [
                'user_id' => $apiUser->id,
                'token_name' => 'digestpipe-api',
                'abilities' => ['digests:read'],
            ])
            ->assertHasNoErrors();

        $plainTextToken = $component->get('newPlainTextToken');
        self::assertIsString($plainTextToken);
        self::assertNotSame('', $plainTextToken);

        $accessToken = PersonalAccessToken::findToken($plainTextToken);
        self::assertInstanceOf(PersonalAccessToken::class, $accessToken);
        self::assertSame('digestpipe-api', $accessToken->name);
        self::assertSame(['digests:read'], $accessToken->abilities);
        self::assertNotSame($plainTextToken, $accessToken->token);
        self::assertSame('digestpipe-api', $component->get('newTokenName'));
        self::assertSame('api@example.test', $component->get('newTokenUserEmail'));
        $component->assertSee('Plain text token');
        $component->assertSee('Copy token');
        $component->assertSeeHtml('role="dialog"');
        Notification::assertNotified('API token を作成しました。');
    }

    public function testTokenListDoesNotExposePlainTextToken(): void
    {
        $this->actingAsAdmin();
        $apiUser = User::factory()->create(['email' => 'api@example.test']);
        $createdToken = app(ApiTokenService::class)->createToken($apiUser, 'digestpipe-api', ['digests:read', 'digests:rate']);

        $this->get('/admin/api-tokens')
            ->assertOk()
            ->assertSee('digestpipe-api')
            ->assertSee('api@example.test')
            ->assertSee('digests:read')
            ->assertSee('digests:rate')
            ->assertSee('api-token-ability-badge')
            ->assertDontSee($createdToken->plainTextToken)
            ->assertDontSee($createdToken->accessToken->token);
    }

    public function testEditTokenActionUpdatesTokenMetadataOnly(): void
    {
        $this->actingAsAdmin();
        $apiUser = User::factory()->create(['email' => 'api@example.test']);
        $createdToken = app(ApiTokenService::class)->createToken($apiUser, 'digestpipe-api', ['digests:read']);
        $tokenHash = $createdToken->accessToken->token;

        /** @var Testable<ListApiTokens> $component */
        $component = Livewire::test(ListApiTokens::class);

        /** @phpstan-ignore-next-line Livewire の table action test helper は runtime に追加されます。 */
        $component
            ->callTableAction('editToken', $createdToken->accessToken, [
                'token_name' => 'digestpipe-rating-api',
                'abilities' => ['digests:read', 'digests:rate'],
            ])
            ->assertHasNoErrors();

        $updatedToken = PersonalAccessToken::query()->findOrFail($createdToken->accessToken->id);
        self::assertSame('digestpipe-rating-api', $updatedToken->name);
        self::assertSame(['digests:read', 'digests:rate'], $updatedToken->abilities);
        self::assertSame($tokenHash, $updatedToken->token);
        self::assertNotSame($createdToken->plainTextToken, $updatedToken->token);
        self::assertNull($component->get('newPlainTextToken'));
        Notification::assertNotified('API token updated.');
    }

    public function testApiTokenServiceRejectsEmptyMetadataAbilities(): void
    {
        $apiUser = User::factory()->create(['email' => 'api@example.test']);
        $createdToken = app(ApiTokenService::class)->createToken($apiUser, 'digestpipe-api', ['digests:read']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('API token abilities must not be empty.');

        app(ApiTokenService::class)->updateTokenMetadata($createdToken->accessToken, 'digestpipe-api', []);
    }

    public function testApiTokenServiceRejectsUnsupportedMetadataAbilities(): void
    {
        $apiUser = User::factory()->create(['email' => 'api@example.test']);
        $createdToken = app(ApiTokenService::class)->createToken($apiUser, 'digestpipe-api', ['digests:read']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported API token ability: digests:admin');

        app(ApiTokenService::class)->updateTokenMetadata($createdToken->accessToken, 'digestpipe-api', ['digests:admin']);
    }

    public function testRevokeTokenActionDeletesSelectedToken(): void
    {
        $this->actingAsAdmin();
        $apiUser = User::factory()->create(['email' => 'api@example.test']);
        $createdToken = app(ApiTokenService::class)->createToken($apiUser, 'digestpipe-api', ['digests:read']);

        /** @var Testable<ListApiTokens> $component */
        $component = Livewire::test(ListApiTokens::class);

        /** @phpstan-ignore-next-line Livewire の table action test helper は runtime に追加されます。 */
        $component
            ->callTableAction('revokeToken', $createdToken->accessToken)
            ->assertHasNoErrors();

        self::assertNull(PersonalAccessToken::query()->find($createdToken->accessToken->id));
        Notification::assertNotified('API token を失効しました。');
    }

    public function testRevokeAllApiTokensActionDeletesAllTokensForUser(): void
    {
        $this->actingAsAdmin();
        $apiUser = User::factory()->create(['email' => 'api@example.test']);
        app(ApiTokenService::class)->createToken($apiUser, 'digestpipe-api', ['digests:read']);
        app(ApiTokenService::class)->createToken($apiUser, 'digestpipe-export', ['digests:read']);

        /** @var Testable<ListApiTokens> $component */
        $component = Livewire::test(ListApiTokens::class);

        /** @phpstan-ignore-next-line Livewire の action test helper は runtime に追加されます。 */
        $component
            ->callAction('revokeAllApiTokens', [
                'user_id' => $apiUser->id,
            ])
            ->assertHasNoErrors();

        self::assertCount(0, $apiUser->tokens()->get());
        Notification::assertNotified('2 API token を失効しました。');
    }

    public function testApiTokenServiceRevokesAllTokensForUser(): void
    {
        $apiUser = User::factory()->create(['email' => 'api@example.test']);
        $tokens = app(ApiTokenService::class);
        $tokens->createToken($apiUser, 'digestpipe-api', ['digests:read']);
        $tokens->createToken($apiUser, 'digestpipe-export', ['digests:read']);

        $revoked = $tokens->revokeAllTokens($apiUser);

        self::assertSame(2, $revoked);
        self::assertCount(0, $apiUser->tokens()->get());
    }

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['email' => 'admin@example.test']);
        $this->actingAs($user);

        return $user;
    }
}
