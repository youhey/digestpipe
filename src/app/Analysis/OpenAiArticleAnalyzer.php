<?php

namespace App\Analysis;

use App\Items\DigestItemTextSelector;
use App\Models\DigestItem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * OpenAI Responses API を使用してDigest Itemの分析結果 JSON を生成
 */
class OpenAiArticleAnalyzer implements ArticleAnalyzer
{
    private const ENDPOINT = 'https://api.openai.com/v1/responses';

    private readonly DigestItemTextSelector $textSelector;

    private readonly ArticleAnalysisValidator $validator;

    /**
     * Constructor
     *
     * @param DigestItemTextSelector|null $textSelector
     * @param ArticleAnalysisValidator|null $validator
     */
    public function __construct(?DigestItemTextSelector $textSelector = null, ?ArticleAnalysisValidator $validator = null)
    {
        $this->textSelector = $textSelector ?? new DigestItemTextSelector();
        $this->validator = $validator ?? new ArticleAnalysisValidator();
    }

    /**
     * Digest Itemを元の言語のまま分析して構造化した JSON を返す
     *
     * @param DigestItem $item
     *
     * @return ArticleAnalysisResult
     *
     * @throws JsonException
     */
    public function analyze(DigestItem $item): ArticleAnalysisResult
    {
        $inputText = $this->textSelector->bodyText($item);

        if ($inputText === null || trim($inputText) === '') {
            throw new ArticleAnalysisException('Article analysis input was empty.');
        }

        $inputText = $this->limitInputText($inputText);

        $payload = [
            'model' => $this->model(),
            'store' => false,
            'instructions' => 'Analyze the article content and return structured digest JSON only. Preserve source-language output. Do not translate into Japanese. Do not invent facts. Use limitations for uncertainty, weak extraction, paywalls, cookie walls, or missing context. The output is an intermediate representation for downstream applications, not final prose for humans.',
            'input' => json_encode([
                'schema_version' => $this->schemaVersion(),
                'source_url' => $item->source_url,
                'title' => $item->title,
                'content' => $inputText,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'digestpipe_article_analysis',
                    'schema' => $this->validator->schema(),
                    'strict' => true,
                ],
            ],
        ];

        $data = $this->requestStructuredJson($payload, $item);

        return new ArticleAnalysisResult($this->validator->validate($data), $this->model());
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function requestStructuredJson(array $payload, DigestItem $item): array
    {
        $apiKey = $this->apiKey();
        $model = $this->model();

        Log::info('OpenAI article analysis request started.', [
            'digest_item_id' => $item->id,
            'source_key' => $item->source_key,
            'model' => $model,
            'schema_version' => $this->schemaVersion(),
            'timeout' => $this->requestTimeout(),
            'max_retries' => $this->maxRetries(),
        ]);

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout($this->requestTimeout())
                ->retry($this->maxRetries() + 1, static fn (int $attempt): int => 250 * $attempt, throw: false)
                ->post(self::ENDPOINT, $payload);
        } catch (ConnectionException $exception) {
            throw new ArticleAnalysisException('OpenAI analysis request failed: connection error.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new ArticleAnalysisException($this->errorMessage($response));
        }

        $jsonText = $this->extractOutputText($response);
        $data = $this->decodeJsonObject($jsonText);

        Log::info('OpenAI article analysis request finished.', [
            'digest_item_id' => $item->id,
            'source_key' => $item->source_key,
            'model' => $model,
            'http_status' => $response->status(),
            'input_tokens' => $this->usageValue($response, 'input_tokens'),
            'output_tokens' => $this->usageValue($response, 'output_tokens'),
        ]);

        return $data;
    }

    private function apiKey(): string
    {
        $value = config('digestpipe.openai.api_key');

        if (! is_string($value) || trim($value) === '') {
            throw new ArticleAnalysisException('OpenAI API key is not configured.');
        }

        return trim($value);
    }

    private function model(): string
    {
        $value = config('digestpipe.analysis.model');

        if (! is_string($value) || trim($value) === '') {
            $value = config('digestpipe.openai.model');
        }

        if (! is_string($value) || trim($value) === '') {
            throw new ArticleAnalysisException('OpenAI analysis model is not configured.');
        }

        return trim($value);
    }

    private function schemaVersion(): string
    {
        $value = config('digestpipe.analysis.schema_version', '1.0');

        return is_string($value) ? $value : '1.0';
    }

    private function limitInputText(string $value): string
    {
        $maxChars = config('digestpipe.analysis.max_input_chars');
        $limit = is_int($maxChars) && $maxChars > 0 ? $maxChars : 8000;

        return substr($value, 0, $limit);
    }

    private function requestTimeout(): int
    {
        $value = config('digestpipe.openai.request_timeout');

        return is_int($value) && $value > 0 ? $value : 120;
    }

    private function maxRetries(): int
    {
        $value = config('digestpipe.openai.max_retries');

        return is_int($value) && $value >= 0 ? $value : 2;
    }

    private function errorMessage(Response $response): string
    {
        $message = $response->json('error.message');

        if (is_string($message) && trim($message) !== '') {
            return 'OpenAI analysis request failed: ' . substr(trim($message), 0, 300);
        }

        return 'OpenAI analysis request failed with HTTP status ' . $response->status() . '.';
    }

    private function extractOutputText(Response $response): string
    {
        $outputText = $response->json('output_text');

        if (is_string($outputText) && trim($outputText) !== '') {
            return $outputText;
        }

        $output = $response->json('output');

        if (! is_array($output)) {
            throw new ArticleAnalysisException('OpenAI analysis response did not include output text.');
        }

        foreach ($output as $outputItem) {
            if (! is_array($outputItem)) {
                continue;
            }

            $content = $outputItem['content'] ?? null;

            if (! is_array($content)) {
                continue;
            }

            foreach ($content as $contentItem) {
                if (! is_array($contentItem)) {
                    continue;
                }

                $text = $contentItem['text'] ?? null;

                if (is_string($text) && trim($text) !== '') {
                    return $text;
                }
            }
        }

        throw new ArticleAnalysisException('OpenAI analysis response output text was empty.');
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(string $jsonText): array
    {
        try {
            $data = json_decode($jsonText, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ArticleAnalysisException('OpenAI analysis response was not valid JSON.', previous: $exception);
        }

        if (! is_array($data)) {
            throw new ArticleAnalysisException('OpenAI analysis response JSON was not an object.');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    private function usageValue(Response $response, string $key): ?int
    {
        $value = $response->json('usage.' . $key);

        return is_int($value) ? $value : null;
    }
}
