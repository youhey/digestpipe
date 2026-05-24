<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * @internal
 */
class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function testCreateApiUserCommandCreatesUserAndIssuesToken(): void
    {
        $exitCode = Artisan::call('digestpipe:users:create-api-user', [
            'email' => 'api@example.test',
            '--name' => 'DigestPipe API User',
        ]);

        self::assertSame(0, $exitCode);
        $output = Artisan::output();
        $token = $this->tokenFromOutput($output);
        self::assertNotSame('', $token);
        self::assertSame(1, substr_count($output, $token));

        $user = User::query()->where('email', 'api@example.test')->firstOrFail();
        self::assertSame('DigestPipe API User', $user->name);
        self::assertFalse(hash_equals($token, $user->password));

        $accessToken = $this->singleTokenForUser($user);
        self::assertSame('digestpipe-api', $accessToken->name);
        self::assertSame(['digests:read'], $accessToken->abilities);
        self::assertNotSame($token, $accessToken->token);
    }

    public function testCreateApiUserCommandReusesExistingUserSafely(): void
    {
        User::query()->create([
            'name' => 'Existing User',
            'email' => 'api@example.test',
            'password' => 'not-a-token',
        ]);

        $exitCode = Artisan::call('digestpipe:users:create-api-user', [
            'email' => 'api@example.test',
            '--name' => 'New Name Is Ignored',
        ]);

        self::assertSame(0, $exitCode);
        self::assertCount(1, User::query()->where('email', 'api@example.test')->get());

        $user = User::query()->where('email', 'api@example.test')->firstOrFail();
        self::assertSame('Existing User', $user->name);
        self::assertCount(1, $user->tokens()->get());
    }

    public function testRotateApiTokenInvalidatesPreviousTokenAndCreatesNewToken(): void
    {
        Artisan::call('digestpipe:users:create-api-user', [
            'email' => 'api@example.test',
        ]);
        $oldToken = $this->tokenFromOutput(Artisan::output());

        $exitCode = Artisan::call('digestpipe:users:rotate-api-token', [
            'email' => 'api@example.test',
        ]);

        self::assertSame(0, $exitCode);
        $newToken = $this->tokenFromOutput(Artisan::output());
        self::assertNotSame($oldToken, $newToken);

        $user = User::query()->where('email', 'api@example.test')->firstOrFail();
        $accessToken = $this->singleTokenForUser($user);
        self::assertSame('digestpipe-api', $accessToken->name);
        self::assertSame(['digests:read'], $accessToken->abilities);
        self::assertNull(PersonalAccessToken::findToken($oldToken));
        self::assertNotNull(PersonalAccessToken::findToken($newToken));
    }

    public function testRotateApiTokenFailsClearlyForMissingUser(): void
    {
        $exitCode = Artisan::call('digestpipe:users:rotate-api-token', [
            'email' => 'missing@example.test',
        ]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('API user was not found', Artisan::output());
    }

    public function testSanctumBearerTokenAuthenticatesPrivateRoute(): void
    {
        $this->registerPrivateTestRoute();

        Artisan::call('digestpipe:users:create-api-user', [
            'email' => 'api@example.test',
        ]);
        $token = $this->tokenFromOutput(Artisan::output());

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/test-auth-foundation')
            ->assertOk()
            ->assertJson([
                'ok' => true,
            ]);
    }

    public function testPrivateRouteRejectsMissingToken(): void
    {
        $this->registerPrivateTestRoute();

        $this->getJson('/api/test-auth-foundation')
            ->assertUnauthorized();
    }

    public function testPrivateRouteRejectsOldRotatedToken(): void
    {
        $this->registerPrivateTestRoute();

        Artisan::call('digestpipe:users:create-api-user', [
            'email' => 'api@example.test',
        ]);
        $oldToken = $this->tokenFromOutput(Artisan::output());

        Artisan::call('digestpipe:users:rotate-api-token', [
            'email' => 'api@example.test',
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->getJson('/api/test-auth-foundation')
            ->assertUnauthorized();
    }

    public function testTokenAbilityOptionCanOverrideDefaultAbility(): void
    {
        Artisan::call('digestpipe:users:create-api-user', [
            'email' => 'api@example.test',
            '--ability' => ['digests:read', 'digests:admin'],
        ]);

        $user = User::query()->where('email', 'api@example.test')->firstOrFail();
        $accessToken = $this->singleTokenForUser($user);

        self::assertSame(['digests:read', 'digests:admin'], $accessToken->abilities);
    }

    private function registerPrivateTestRoute(): void
    {
        Route::middleware(['auth:sanctum', 'abilities:digests:read'])
            ->get('/api/test-auth-foundation', static fn (): array => ['ok' => true]);
    }

    private function tokenFromOutput(string $output): string
    {
        $matches = [];
        $matched = preg_match('/Token: (\d+\|[A-Za-z0-9]+)/', $output, $matches);

        if ($matched !== 1) {
            self::fail('Command output did not contain an API token.');
        }

        return $matches[1];
    }

    private function singleTokenForUser(User $user): PersonalAccessToken
    {
        $tokens = $user->tokens()->get();
        self::assertCount(1, $tokens);
        $token = $tokens->first();
        self::assertInstanceOf(PersonalAccessToken::class, $token);

        return $token;
    }
}
