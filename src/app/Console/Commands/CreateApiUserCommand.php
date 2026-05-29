<?php

namespace App\Console\Commands;

use App\ApiTokens\ApiTokenService;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Private API 用ユーザーを作成して personal access token を発行
 */
class CreateApiUserCommand extends Command
{
    protected $signature = 'digestpipe:users:create-api-user
        {email : API user email address}
        {--name=DigestPipe User : User display name}
        {--token-name=digestpipe-api : Sanctum token name}
        {--ability=* : Token ability. Defaults to digests:read}';

    protected $description = 'Create or reuse a private API user and issue a Sanctum token.';

    /**
     * API user を作成または再利用して、新しい token を一度だけ表示する
     *
     * @return int success=0 or invalid=2
     */
    public function handle(ApiTokenService $tokens): int
    {
        try {
            $email = $this->emailArgument();
            $name = $this->nameOption();
            $tokenName = $this->tokenNameOption();
            $abilities = $this->abilityOptions();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(Str::random(64)),
            ],
        );

        $token = $tokens->createToken($user, $tokenName, $abilities)->plainTextToken;

        $this->line('API token created. Store it now; it will not be shown again. Token: ' . $token);

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

    private function nameOption(): string
    {
        $value = $this->option('name');

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('The --name option must not be empty.');
        }

        return trim($value);
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
