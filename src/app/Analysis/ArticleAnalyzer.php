<?php

namespace App\Analysis;

use App\Models\NewsItem;

/**
 * ニュース記事の分析結果 JSON を生成
 */
interface ArticleAnalyzer
{
    /**
     * ニュース記事アイテムを元の言語のまま分析して構造化した JSON を返す
     *
     * @param NewsItem $item
     *
     * @return ArticleAnalysisResult
     */
    public function analyze(NewsItem $item): ArticleAnalysisResult;
}
