<?php

namespace App\Processing;

use App\Models\NewsItem;

/**
 * News itemの翻訳と要約を生成する処理serviceです。
 */
interface NewsAiProcessor
{
    /**
     * News itemから日本語翻訳結果を生成します。
     */
    public function translate(NewsItem $item): NewsTranslationResult;

    /**
     * News itemから要約結果を生成します。
     */
    public function summarize(NewsItem $item): NewsSummaryResult;
}
