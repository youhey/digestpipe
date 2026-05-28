<?php

namespace App\Items;

/**
 * Selection keyword の一致条件と score を表す値オブジェクト
 */
class SelectionKeywordRule
{
    /** @var string keyword 文字列 */
    public readonly string $keyword;

    /** @var int 一致時に加算する score */
    public readonly int $score;

    /** @var string keyword の一致方式 */
    public readonly string $matchMode;

    /**
     * Constructor
     */
    public function __construct(string $keyword, int $score, string $matchMode)
    {
        $this->keyword = $keyword;
        $this->score = $score;
        $this->matchMode = $matchMode;
    }
}
