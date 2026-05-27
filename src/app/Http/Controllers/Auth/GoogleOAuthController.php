<?php

namespace App\Http\Controllers\Auth;

use App\Admin\AdminEmailAllowList;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * Google OAuth による管理画面ログインを処理します。
 */
class GoogleOAuthController extends Controller
{
    /**
     * Google OAuth の認可画面へリダイレクトします。
     *
     * @return RedirectResponse|SymfonyRedirectResponse
     */
    public function redirect(): RedirectResponse|SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Google OAuth の callback を受け取り、allow-list 済みユーザーだけをログインさせます。
     *
     * @param AdminEmailAllowList $allowList
     *
     * @return RedirectResponse
     */
    public function callback(AdminEmailAllowList $allowList): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();
        $email = $this->normalizedEmail($googleUser);

        abort_if(! $allowList->allows($email), 403);

        $user = User::query()->firstOrNew([
            'email' => $email,
        ]);

        $user->forceFill([
            'name' => $googleUser->getName() !== null ? $googleUser->getName() : $email,
            'email_verified_at' => $user->email_verified_at ?? Carbon::now(),
        ]);

        if (! $user->exists) {
            $user->password = Str::random(64);
        }

        $user->save();

        Auth::guard('web')->login($user);

        return redirect('/admin');
    }

    private function normalizedEmail(SocialiteUser $googleUser): ?string
    {
        $email = $googleUser->getEmail();

        if ($email === null || trim($email) === '') {
            return null;
        }

        return strtolower(trim($email));
    }
}
