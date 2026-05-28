<?php

namespace App\Translation;

/**
 * 一時翻訳結果と入力切り詰め有無を表します。
 */
class TranslationResult
{
    /** @var string 翻訳済み text */
    public string $text;

    /** @var bool 翻訳前に入力を切り詰めたか */
    public bool $truncated;

    /**
     * Constructor
     *
     * @param string $text
     * @param bool $truncated
     */
    public function __construct(string $text, bool $truncated)
    {
        $this->text = $text;
        $this->truncated = $truncated;
    }
}
