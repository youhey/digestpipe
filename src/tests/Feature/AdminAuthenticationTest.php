<?php

namespace Tests\Feature;

use App\Admin\AdminEmailAllowList;
use App\Models\FeedSource;
use App\Models\SelectionKeyword;
use App\Models\User;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use LogicException;
use Tests\TestCase;

/**
 * @internal
 */
class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function testAllowedGoogleEmailCanLogIn(): void
    {
        config(['digestpipe.admin.allowed_emails' => ['admin@example.test']]);
        $this->mockGoogleUser('Admin User', 'admin@example.test');

        $this->get(route('auth.google.callback'))
            ->assertRedirect('/admin');

        $user = User::query()->where('email', 'admin@example.test')->firstOrFail();
        self::assertSame('Admin User', $user->name);
        self::assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function testDisallowedGoogleEmailCannotLogIn(): void
    {
        config(['digestpipe.admin.allowed_emails' => ['admin@example.test']]);
        $this->mockGoogleUser('Other User', 'other@example.test');

        $this->get(route('auth.google.callback'))
            ->assertForbidden();

        $this->assertGuest();
        self::assertNull(User::query()->where('email', 'other@example.test')->first());
    }

    public function testEmailMatchingIsCaseInsensitive(): void
    {
        config(['digestpipe.admin.allowed_emails' => ['Admin@Example.Test']]);

        self::assertTrue(app(AdminEmailAllowList::class)->allows('admin@example.test'));
    }

    public function testWhitespaceInAllowListConfigIsIgnored(): void
    {
        config(['digestpipe.admin.allowed_emails' => [' admin@example.test ']]);

        self::assertTrue(app(AdminEmailAllowList::class)->allows('admin@example.test'));
    }

    public function testEmptyAllowListDeniesAccess(): void
    {
        config(['digestpipe.admin.allowed_emails' => []]);

        self::assertFalse(app(AdminEmailAllowList::class)->allows('admin@example.test'));
    }

    public function testAllowedLoggedInUserCanAccessFilamentPanel(): void
    {
        config(['digestpipe.admin.allowed_emails' => ['admin@example.test']]);
        $user = User::factory()->create([
            'email' => 'admin@example.test',
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk();
    }

    public function testUserRemovedFromAllowListCannotAccessFilamentPanel(): void
    {
        config(['digestpipe.admin.allowed_emails' => []]);
        $user = User::factory()->create([
            'email' => 'admin@example.test',
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function testCanAccessPanelUsesAllowList(): void
    {
        config(['digestpipe.admin.allowed_emails' => ['admin@example.test']]);
        $allowedUser = User::factory()->make([
            'email' => 'admin@example.test',
        ]);
        $disallowedUser = User::factory()->make([
            'email' => 'other@example.test',
        ]);

        self::assertTrue($allowedUser->canAccessPanel(Panel::make()));
        self::assertFalse($disallowedUser->canAccessPanel(Panel::make()));
    }

    public function testGoogleOAuthTokensAreNotStored(): void
    {
        config(['digestpipe.admin.allowed_emails' => ['admin@example.test']]);
        $this->mockGoogleUser('Admin User', 'admin@example.test');

        $this->get(route('auth.google.callback'))
            ->assertRedirect('/admin');

        $user = User::query()->where('email', 'admin@example.test')->firstOrFail();
        $attributes = $user->getAttributes();

        self::assertArrayNotHasKey('google_token', $attributes);
        self::assertArrayNotHasKey('access_token', $attributes);
        self::assertArrayNotHasKey('refresh_token', $attributes);
    }

    public function testFilamentLoginRouteRedirectsToGoogleOAuth(): void
    {
        $this->get('/admin/login')
            ->assertRedirect(route('auth.google.redirect'));
    }

    public function testGoogleRedirectRouteStartsOAuthFlow(): void
    {
        $provider = new class() implements SocialiteProvider {
            public function redirect(): RedirectResponse
            {
                return redirect('https://accounts.google.example.test/oauth');
            }

            public function user(): SocialiteUser
            {
                throw new LogicException('Not used in this test.');
            }
        };

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn($provider);

        $this->get(route('auth.google.redirect'))
            ->assertRedirect('https://accounts.google.example.test/oauth');
    }

    public function testLocalAdminLoginRouteReturnsNotFoundInProductionEnvironment(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        $this->enableLocalAdminDevLogin('admin@example.test');

        $this->get(route('local.admin.login'))
            ->assertNotFound();

        $this->assertGuest();
    }

    public function testLocalAdminLoginRouteReturnsNotFoundWhenDisabled(): void
    {
        config([
            'digestpipe.admin.allowed_emails' => ['admin@example.test'],
            'digestpipe.admin.dev_login.enabled' => false,
            'digestpipe.admin.dev_login.email' => 'admin@example.test',
        ]);

        $this->get(route('local.admin.login'))
            ->assertNotFound();

        $this->assertGuest();
    }

    public function testLocalAdminLoginRouteReturnsNotFoundWhenEmailIsMissing(): void
    {
        config([
            'digestpipe.admin.allowed_emails' => ['admin@example.test'],
            'digestpipe.admin.dev_login.enabled' => true,
            'digestpipe.admin.dev_login.email' => '',
        ]);

        $this->get(route('local.admin.login'))
            ->assertNotFound();

        $this->assertGuest();
    }

    public function testLocalAdminLoginRouteDeniesDevEmailOutsideAllowList(): void
    {
        config([
            'digestpipe.admin.allowed_emails' => ['admin@example.test'],
            'digestpipe.admin.dev_login.enabled' => true,
            'digestpipe.admin.dev_login.email' => 'other@example.test',
        ]);

        $this->get(route('local.admin.login'))
            ->assertForbidden();

        $this->assertGuest();
        self::assertNull(User::query()->where('email', 'other@example.test')->first());
    }

    public function testLocalAdminLoginRouteLogsInAllowedDevUser(): void
    {
        $this->enableLocalAdminDevLogin('admin@example.test');

        $this->get(route('local.admin.login'))
            ->assertRedirect('/admin');

        $user = User::query()->where('email', 'admin@example.test')->firstOrFail();
        self::assertSame('Local Admin', $user->name);
        self::assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function testLocalAdminLoginUserCanAccessFilamentPanel(): void
    {
        $this->enableLocalAdminDevLogin('admin@example.test');

        $this->get(route('local.admin.login'))
            ->assertRedirect('/admin');

        $this->get('/admin')
            ->assertOk();
    }

    public function testLocalAdminLoginUserRemovedFromAllowListCannotAccessFilamentPanel(): void
    {
        $this->enableLocalAdminDevLogin('admin@example.test');

        $this->get(route('local.admin.login'))
            ->assertRedirect('/admin');

        config(['digestpipe.admin.allowed_emails' => []]);

        $this->get('/admin')
            ->assertForbidden();
    }

    public function testLocalAdminLoginDoesNotStoreOAuthTokens(): void
    {
        $this->enableLocalAdminDevLogin('admin@example.test');

        $this->get(route('local.admin.login'))
            ->assertRedirect('/admin');

        $user = User::query()->where('email', 'admin@example.test')->firstOrFail();
        $attributes = $user->getAttributes();

        self::assertArrayNotHasKey('google_token', $attributes);
        self::assertArrayNotHasKey('access_token', $attributes);
        self::assertArrayNotHasKey('refresh_token', $attributes);
    }

    public function testAllowedAdminCanRenderFeedSourceResource(): void
    {
        config(['digestpipe.admin.allowed_emails' => ['admin@example.test']]);
        $user = User::factory()->create([
            'email' => 'admin@example.test',
        ]);
        FeedSource::query()->create([
            'key' => 'hacker_news',
            'name' => 'Hacker News',
            'url' => 'https://news.ycombinator.com/rss',
            'language' => 'en',
            'enabled' => true,
            'analysis_enabled' => true,
            'tier' => 'core',
            'category' => 'aggregator',
            'sort_order' => 10,
        ]);

        $this->actingAs($user)
            ->get('/admin/feed-sources')
            ->assertOk()
            ->assertSee('Feed Sources')
            ->assertSee('hacker_news');
    }

    public function testAllowedAdminCanRenderDashboardWidgets(): void
    {
        config(['digestpipe.admin.allowed_emails' => ['admin@example.test']]);
        $user = User::factory()->create(['email' => 'admin@example.test']);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Dashboard');
    }

    public function testAllowedAdminCanRenderSelectionKeywordResource(): void
    {
        config(['digestpipe.admin.allowed_emails' => ['admin@example.test']]);
        $user = User::factory()->create(['email' => 'admin@example.test']);
        SelectionKeyword::query()->create([
            'keyword' => 'Laravel',
            'type' => 'positive',
            'score' => 15,
            'enabled' => true,
            'locale' => 'any',
            'category' => 'laravel',
            'sort_order' => 10,
        ]);

        $this->actingAs($user)
            ->get('/admin/selection-keywords')
            ->assertOk()
            ->assertSee('Selection Keywords')
            ->assertSee('Laravel');
    }

    private function mockGoogleUser(string $name, string $email): void
    {
        $googleUser = new class($name, $email) implements SocialiteUser {
            public function __construct(
                private readonly string $name,
                private readonly string $email,
            ) {
            }

            public function getId(): string
            {
                return 'google-user-id';
            }

            public function getNickname(): ?string
            {
                return null;
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function getEmail(): string
            {
                return $this->email;
            }

            public function getAvatar(): string
            {
                return 'https://example.test/avatar.png';
            }
        };

        $provider = new class($googleUser) implements SocialiteProvider {
            public function __construct(
                private readonly SocialiteUser $googleUser,
            ) {
            }

            public function redirect(): RedirectResponse
            {
                throw new LogicException('Not used in this test.');
            }

            public function user(): SocialiteUser
            {
                return $this->googleUser;
            }
        };

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn($provider);
    }

    private function enableLocalAdminDevLogin(string $email): void
    {
        config([
            'digestpipe.admin.allowed_emails' => [$email],
            'digestpipe.admin.dev_login.enabled' => true,
            'digestpipe.admin.dev_login.email' => $email,
        ]);
    }
}
