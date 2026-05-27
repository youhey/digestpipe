<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Filament\Panel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * local/testing 環境だけで管理画面用の開発ログインを処理します。
 */
class LocalAdminLoginController extends Controller
{
    /**
     * 設定済みの開発用ユーザーで session login し、管理画面へリダイレクトします。
     *
     * @return RedirectResponse
     */
    public function __invoke(): RedirectResponse
    {
        abort_unless(app()->environment('local', 'testing'), 404);
        abort_unless((bool) config('digestpipe.admin.dev_login.enabled', false), 404);

        $email = $this->configuredEmail();

        abort_if($email === null, 404);

        $user = User::query()->firstOrNew([
            'email' => $email,
        ]);

        $user->forceFill([
            'name' => 'Local Admin',
            'email_verified_at' => $user->email_verified_at ?? Carbon::now(),
        ]);

        if (! $user->exists) {
            $user->password = Str::random(64);
        }

        abort_unless($user->canAccessPanel(Panel::make()), 403);

        $user->save();

        Auth::guard('web')->login($user);

        Log::info('Local admin dev login used.');

        return redirect('/admin');
    }

    private function configuredEmail(): ?string
    {
        $email = config('digestpipe.admin.dev_login.email');

        if (! is_string($email) || trim($email) === '') {
            return null;
        }

        return strtolower(trim($email));
    }
}
