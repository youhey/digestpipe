<?php

namespace App\Feeds;

/**
 * RSS フィード情報源の取得結果
 */
class FetchedFeed
{
    /** @var int HTTP Status Code */
    public readonly int $statusCode;

    /** @var string Response Body */
    public readonly string $body;

    /** @var bool HTTP Client がリクエストに成功していれば `TRUE` 失敗していれば `FALSE` */
    public readonly bool $successful;

    /**
     * Constructor
     *
     * @param int $statusCode
     * @param string $body
     * @param bool $successful
     */
    public function __construct(int $statusCode, string $body, bool $successful)
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->successful = $successful;
    }
}
