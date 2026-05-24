<?php

namespace App\Processing;

use App\Models\NewsItem;

/**
 * 外部APIを呼ばずに決定的な翻訳・要約結果を返すstub serviceです。
 */
class FakeNewsAiProcessor implements NewsAiProcessor
{
    private readonly NewsItemTextSelector $textSelector;

    /**
     * Fake AI processorを作成します。
     */
    public function __construct(?NewsItemTextSelector $textSelector = null)
    {
        $this->textSelector = $textSelector ?? new NewsItemTextSelector();
    }

    /**
     * 元titleとdescriptionに日本語処理済みであることを示すprefixを付与します。
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
     * 翻訳済みtextから短い固定形式のsummaryを作成します。
     */
    public function summarize(NewsItem $item): NewsSummaryResult
    {
        $text = trim(($item->translated_title ?? $item->title) . ' ' . ($item->translated_description ?? $this->textSelector->bodyText($item) ?? ''));

        return new NewsSummaryResult('Summary: ' . substr($text, 0, 160));
    }
}
