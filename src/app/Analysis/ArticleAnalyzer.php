<?php

namespace App\Analysis;

use App\Models\DigestItem;

/**
 * Digest Itemの分析結果 JSON を生成
 */
interface ArticleAnalyzer
{
    /**
     * Digest Itemを元の言語のまま分析して構造化した JSON を返す
     *
     * @param DigestItem $item
     *
     * @return ArticleAnalysisResult
     */
    public function analyze(DigestItem $item): ArticleAnalysisResult;
}
