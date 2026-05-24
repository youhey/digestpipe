<?php

namespace App\Processing;

use App\Models\NewsItem;

/**
 * 外部 API を使用しないでニュース記事アイテムの翻訳と要約を生成
 */
class FakeNewsAiProcessor implements NewsAiProcessor
{
    private readonly NewsItemTextSelector $textSelector;

    /**
     * Constructor
     *
     * @param NewsItemTextSelector|null $textSelector
     */
    public function __construct(?NewsItemTextSelector $textSelector = null)
    {
        $this->textSelector = $textSelector ?? new NewsItemTextSelector();
    }

    /**
     * タイトルと本文に日本語翻訳済みであることを示す Prefix を付与した結果を返す
     *
     * @param NewsItem $item
     *
     * @return NewsTranslationResult
     */
    public function translate(NewsItem $item): NewsTranslationResult
    {
        $bodyText = $this->textSelector->bodyText($item);

        return new NewsTranslationResult(
            title: '[ja] ' . $item->title,
            description: $bodyText === null ? null : '[ja] ' . $bodyText,
        );
    }

    /**
     * 翻訳済みテキストから短い固定形式の要約を作成して結果を返す
     *
     * @param NewsItem $item
     *
     * @return NewsSummaryResult
     */
    public function summarize(NewsItem $item): NewsSummaryResult
    {
        $text = trim(($item->translated_title ?? $item->title) . ' ' . ($item->translated_description ?? $this->textSelector->bodyText($item) ?? ''));

        return new NewsSummaryResult('Summary: ' . substr($text, 0, 160));
    }
}
