<?php

namespace App\Analysis;

/**
 * Digest Itemの分析結果 JSON Schema v1 を検証して正規化
 */
class ArticleAnalysisValidator
{
    /**
     * Analysis JSON を検証して保存可能な Shape へ正規化
     *
     * @param array<string, mixed> $json
     *
     * @return array<string, mixed>
     */
    public function validate(array $json): array
    {
        $schemaVersion = $json['schema_version'] ?? null;

        if ($schemaVersion !== $this->schemaVersion()) {
            throw new ArticleAnalysisException('Article analysis response schema_version was invalid.');
        }

        $this->requireArray($json, 'title');
        $this->requireArray($json, 'content');
        $this->requireArray($json, 'classification');

        $title = $json['title'];
        $content = $json['content'];
        $classification = $json['classification'];

        if (! is_array($title) || ! is_array($content) || ! is_array($classification)) {
            throw new ArticleAnalysisException('Article analysis response shape was invalid.');
        }

        /** @var array<string, mixed> $title */
        /** @var array<string, mixed> $content */
        /** @var array<string, mixed> $classification */
        $this->requireString($json, 'source_language');
        $this->requireString($title, 'original');
        $this->requireString($title, 'normalized');
        $this->requireString($content, 'brief');
        $this->requireString($content, 'detailed_summary');
        $this->requireStringList($content, 'key_points');
        $this->requireString($classification, 'content_type');
        $this->requireStringList($classification, 'topics');
        $this->requireStringList($classification, 'entities', true);
        $this->requireIntRange($classification, 'importance', 1, 5);
        $this->requireFloatRange($classification, 'confidence', 0.0, 1.0);

        return $json;
    }

    /**
     * Analysis JSON schema v1 を返す
     *
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'schema_version' => ['type' => 'string'],
                'source_language' => ['type' => 'string'],
                'title' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'original' => ['type' => 'string'],
                        'normalized' => ['type' => 'string'],
                    ],
                    'required' => ['original', 'normalized'],
                ],
                'content' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'brief' => ['type' => 'string'],
                        'detailed_summary' => ['type' => 'string'],
                        'key_points' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                        'background' => ['type' => ['string', 'null']],
                        'why_it_matters' => ['type' => ['string', 'null']],
                        'limitations' => ['type' => ['string', 'null']],
                    ],
                    'required' => ['brief', 'detailed_summary', 'key_points', 'background', 'why_it_matters', 'limitations'],
                ],
                'classification' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'content_type' => ['type' => 'string'],
                        'topics' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                        'entities' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                        'importance' => ['type' => 'integer'],
                        'confidence' => ['type' => 'number'],
                    ],
                    'required' => ['content_type', 'topics', 'entities', 'importance', 'confidence'],
                ],
            ],
            'required' => ['schema_version', 'source_language', 'title', 'content', 'classification'],
        ];
    }

    private function schemaVersion(): string
    {
        $value = config('digestpipe.analysis.schema_version', '1.0');

        return is_string($value) ? $value : '1.0';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireArray(array $data, string $key): void
    {
        if (! is_array($data[$key] ?? null)) {
            throw new ArticleAnalysisException("Article analysis response [{$key}] was missing or invalid.");
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireString(array $data, string $key): void
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new ArticleAnalysisException("Article analysis response [{$key}] was missing or invalid.");
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireStringList(array $data, string $key, bool $allowEmpty = false): void
    {
        $value = $data[$key] ?? null;

        if (! is_array($value) || (! $allowEmpty && $value === [])) {
            throw new ArticleAnalysisException("Article analysis response [{$key}] was missing or invalid.");
        }

        foreach ($value as $item) {
            if (! is_string($item) || trim($item) === '') {
                throw new ArticleAnalysisException("Article analysis response [{$key}] contained an invalid item.");
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireIntRange(array $data, string $key, int $min, int $max): void
    {
        $value = $data[$key] ?? null;

        if (! is_int($value) || $value < $min || $value > $max) {
            throw new ArticleAnalysisException("Article analysis response [{$key}] was outside the allowed range.");
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireFloatRange(array $data, string $key, float $min, float $max): void
    {
        $value = $data[$key] ?? null;

        if (! is_int($value) && ! is_float($value)) {
            throw new ArticleAnalysisException("Article analysis response [{$key}] was outside the allowed range.");
        }

        $floatValue = (float) $value;

        if ($floatValue < $min || $floatValue > $max) {
            throw new ArticleAnalysisException("Article analysis response [{$key}] was outside the allowed range.");
        }
    }
}
