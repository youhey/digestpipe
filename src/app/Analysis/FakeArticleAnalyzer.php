<?php

namespace App\Analysis;

use App\Items\DigestItemTextSelector;
use App\Models\DigestItem;

/**
 * 外部 API を利用しないでDigest Itemの分析結果 JSON を生成
 */
class FakeArticleAnalyzer implements ArticleAnalyzer
{
    private readonly DigestItemTextSelector $textSelector;

    /**
     * Constructor
     *
     * @param DigestItemTextSelector|null $textSelector
     */
    public function __construct(?DigestItemTextSelector $textSelector = null)
    {
        $this->textSelector = $textSelector ?? new DigestItemTextSelector();
    }

    /**
     * Digest Itemから固定形式の構造化した JSON を返す
     */
    public function analyze(DigestItem $item): ArticleAnalysisResult
    {
        $text = $this->textSelector->bodyText($item) ?? $item->title;

        return new ArticleAnalysisResult([
            'schema_version' => $this->schemaVersion(),
            'source_language' => 'en',
            'title' => [
                'original' => $item->title,
                'normalized' => $item->title,
            ],
            'content' => [
                'brief' => substr($text, 0, 160),
                'detailed_summary' => substr($text, 0, 1200),
                'key_points' => [
                    substr($text, 0, 160),
                ],
                'background' => null,
                'why_it_matters' => 'This item may be relevant to downstream digest consumers.',
                'limitations' => $item->article_content_text === null ? 'Article content was not extracted; fallback input was used.' : null,
            ],
            'classification' => [
                'content_type' => 'news_article',
                'topics' => ['general'],
                'entities' => [],
                'importance' => 3,
                'confidence' => 0.8,
            ],
        ], 'fake');
    }

    private function schemaVersion(): string
    {
        $value = config('digestpipe.analysis.schema_version', '1.0');

        return is_string($value) ? $value : '1.0';
    }
}
