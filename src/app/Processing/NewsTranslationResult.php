<?php

namespace App\Processing;

/**
 * News item翻訳処理の結果です。
 */
class NewsTranslationResult
{
    /** 翻訳済みtitleです。 */
    public readonly string $title;

    /** 翻訳済みdescriptionです。 */
    public readonly ?string $description;

    /**
     * 翻訳結果を作成します。
     */
    public function __construct(string $title, ?string $description)
    {
        $this->title = $title;
        $this->description = $description;
    }
}
