<?php

namespace App\Console\Commands;

use App\ApiTokens\ApiTokenService;
use App\Models\User;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Private API 用 personal access token を再発行
 */
class RotateApiTokenCommand extends Command
{
    protected $signature = 'digestpipe:users:rotate-api-token
        {email : API user email address}
        {--token-name=digestpipe-api : Sanctum token name}
        {--ability=* : Token ability. Defaults to digests:read}';

    protected $description = 'Rotate a Sanctum token for a private API user.';

    /**
     * 既存 token を失効させて、新しい token を一度だけ表示する
     *
     * @return int success=0 or failure=1 or invalid=2
     */
    public function handle(ApiTokenService $tokens): int
    {
        try {
            $email = $this->emailArgument();
            $tokenName = $this->tokenNameOption();
            $abilities = $this->abilityOptions();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            $this->error('API user was not found for the given email address.');

            return self::FAILURE;
        }

        $tokens->revokeTokensByName($user, $tokenName);

        $token = $tokens->createToken($user, $tokenName, $abilities)->plainTextToken;

        $this->line('API token rotated. Store it now; it will not be shown again. Token: ' . $token);

        return self::SUCCESS;
    }

    private function emailArgument(): string
    {
        $value = $this->argument('email');

        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('The email argument must be a valid email address.');
        }

        return $value;
    }

    private function tokenNameOption(): string
    {
        $value = $this->option('token-name');

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('The --token-name option must not be empty.');
        }

        return trim($value);
    }

    /**
     * @return list<string>
     */
    private function abilityOptions(): array
    {
        $values = $this->option('ability');

        if ($values === []) {
            return ['digests:read'];
        }

        $abilities = [];

        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException('The --ability option must not be empty.');
            }

            $abilities[] = trim($value);
        }

        return $abilities;
    }
}
