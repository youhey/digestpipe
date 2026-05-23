<?php

namespace App\Feeds;

/**
 * Feed sourceへのHTTP fetch結果です。
 */
class FetchedFeed
{
    /** HTTP status codeです。 */
    public readonly int $statusCode;

    /** Response bodyです。ログには出さない前提です。 */
    public readonly string $body;

    /** Laravel HTTP client上の成功判定です。 */
    public readonly bool $successful;

    /**
     * HTTP fetch結果を作成します。
     */
    public function __construct(int $statusCode, string $body, bool $successful)
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->successful = $successful;
    }
}
