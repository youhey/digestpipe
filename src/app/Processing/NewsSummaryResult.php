<?php

namespace App\Processing;

/**
 * ニュース記事アイテムの要約結果
 */
class NewsSummaryResult
{
    /** @var string 本文の短い要約 */
    public readonly string $summary;

    /**
     * Constructor
     *
     * @param string $summary
     */
    public function __construct(string $summary)
    {
        $this->summary = $summary;
    }
}
