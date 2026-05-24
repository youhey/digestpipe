<?php

namespace App\Processing;

use App\Models\NewsItem;

/**
 * AI を利用してニュース記事アイテムの翻訳と要約を生成
 */
interface NewsAiProcessor
{
    /**
     * ニュース記事アイテムを日本語に翻訳した結果を返す
     *
     * @param NewsItem $item
     *
     * @return NewsTranslationResult
     */
    public function translate(NewsItem $item): NewsTranslationResult;

    /**
     * ニュース記事アイテムを要約した結果を返す
     *
     * @param NewsItem $item
     *
     * @return NewsSummaryResult
     */
    public function summarize(NewsItem $item): NewsSummaryResult;
}
