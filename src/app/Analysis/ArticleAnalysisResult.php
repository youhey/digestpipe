<?php

namespace App\Analysis;

/**
 * Article analysis serviceが返す構造化digest JSONとmodel名です。
 */
class ArticleAnalysisResult
{
    /**
     * Analysis JSON schemaに沿った結果です。
     *
     * @var array<string, mixed>
     */
    public readonly array $json;

    /** Analysisに使用したmodel名です。 */
    public readonly string $model;

    /**
     * Analysis結果を作成します。
     *
     * @param array<string, mixed> $json
     */
    public function __construct(array $json, string $model)
    {
        $this->json = $json;
        $this->model = $model;
    }
}
