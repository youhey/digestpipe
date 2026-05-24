<?php

namespace App\Analysis;

use App\Models\NewsItem;

/**
 * News itemから構造化digest JSONを生成するanalysis serviceです。
 */
interface ArticleAnalyzer
{
    /**
     * News itemをsource languageのまま分析し、構造化JSONを返します。
     */
    public function analyze(NewsItem $item): ArticleAnalysisResult;
}
