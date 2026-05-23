<?php

namespace App\Processing;

/**
 * News item要約処理の結果です。
 */
class NewsSummaryResult
{
    /** 保存する短い要約文です。 */
    public readonly string $summary;

    /**
     * 要約結果を作成します。
     */
    public function __construct(string $summary)
    {
        $this->summary = $summary;
    }
}
