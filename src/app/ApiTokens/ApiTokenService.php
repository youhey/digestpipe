<?php

namespace App\ApiTokens;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Web API 用 Sanctum token の発行と失効を扱います。
 */
class ApiTokenService
{
    /**
     * UI で選択可能な token ability を返します。
     *
     * @return array<string, string>
     */
    public function allowedAbilities(): array
    {
        return [
            'digests:read' => 'digests:read',
        ];
    }

    /**
     * API token の既定 ability を返します。
     *
     * @return list<string>
     */
    public function defaultAbilities(): array
    {
        return ['digests:read'];
    }

    /**
     * User に新しい API token を発行します。
     *
     * @param User $user
     * @param string $name
     * @param list<string> $abilities
     *
     * @return CreatedApiToken
     */
    public function createToken(User $user, string $name, array $abilities): CreatedApiToken
    {
        return new CreatedApiToken($user->createToken($name, $abilities));
    }

    /**
     * 指定した API token を失効させます。
     *
     * @param PersonalAccessToken $token
     */
    public function revokeToken(PersonalAccessToken $token): void
    {
        $token->delete();
    }

    /**
     * User の全 API token を失効させます。
     *
     * @param User $user
     *
     * @return int 失効した token 数
     */
    public function revokeAllTokens(User $user): int
    {
        return DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->delete();
    }

    /**
     * User の指定 token 名の API token を失効させます。
     *
     * @param User $user
     * @param string $name
     *
     * @return int 失効した token 数
     */
    public function revokeTokensByName(User $user, string $name): int
    {
        return DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->where('name', $name)
            ->delete();
    }
}
