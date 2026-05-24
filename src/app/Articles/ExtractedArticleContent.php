<?php

namespace App\Articles;

/**
 * HTMLから抽出した記事本文です。
 */
class ExtractedArticleContent
{
    /** 抽出済みの正規化済み本文です。 */
    public readonly string $text;

    /** 抽出済み本文の文字数です。 */
    public readonly int $characterCount;

    /**
     * 抽出結果を作成します。
     */
    public function __construct(string $text)
    {
        $this->text = $text;
        $this->characterCount = strlen($text);
    }
}
