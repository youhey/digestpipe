<?php

namespace App\Analysis;

/**
 * Digest Itemの分析結果を構造化した JSON および使用したモデル名
 */
class ArticleAnalysisResult
{
    /** @var array<string, mixed> Analysis JSON Schema に準拠した分析結果 JSON */
    public readonly array $json;

    /** @var string Analysis に使用したモデル名 */
    public readonly string $model;

    /**
     * Constructor
     *
     * @param array<string, mixed> $json
     * @param string $model
     */
    public function __construct(array $json, string $model)
    {
        $this->json = $json;
        $this->model = $model;
    }
}
