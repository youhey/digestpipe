<?php

namespace App\ApiTokens;

use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * 発行直後に一度だけ扱う API token 情報
 */
class CreatedApiToken
{
    /** @var PersonalAccessToken 発行された Sanctum token metadata */
    public PersonalAccessToken $accessToken;

    /** @var string 発行直後に一度だけ表示する plain text token */
    public string $plainTextToken;

    /**
     * Constructor
     */
    public function __construct(NewAccessToken $token)
    {
        $this->accessToken = $token->accessToken;
        $this->plainTextToken = $token->plainTextToken;
    }
}
