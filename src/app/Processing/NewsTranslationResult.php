<?php

namespace App\Processing;

/**
 * ニュース記事アイテムの翻訳結果
 */
class NewsTranslationResult
{
    /** @var string 翻訳済みタイトル */
    public readonly string $title;

    /** @var string|null 翻訳済み本文 */
    public readonly ?string $description;

    /**
     * Constructor
     *
     * @param string $title
     * @param string|null $description
     */
    public function __construct(string $title, ?string $description)
    {
        $this->title = $title;
        $this->description = $description;
    }
}
