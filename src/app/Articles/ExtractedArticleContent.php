<?php

namespace App\Articles;

/**
 * HTML から抽出した記事本文
 */
class ExtractedArticleContent
{
    /** @var string 正規化済みの本文 */
    public readonly string $text;

    /** @var int 本文の文字数 */
    public readonly int $characterCount;

    /**
     * Constructor
     *
     * @param string $text
     */
    public function __construct(string $text)
    {
        $this->text = $text;
        $this->characterCount = strlen($text);
    }
}
