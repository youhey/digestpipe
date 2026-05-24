<?php

namespace App\Processing;

use App\Models\NewsItem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * OpenAI Responses APIを使ってnews itemの翻訳と要約を生成します。
 */
class OpenAiNewsAiProcessor implements NewsAiProcessor
{
    private const ENDPOINT = 'https://api.openai.com/v1/responses';

    private readonly NewsItemTextSelector $textSelector;

    /**
     * OpenAI-backed AI processorを作成します。
     */
    public function __construct(?NewsItemTextSelector $textSelector = null)
    {
        $this->textSelector = $textSelector ?? new NewsItemTextSelector();
    }

    /**
     * News itemから日本語翻訳結果を生成します。
     */
    public function translate(NewsItem $item): NewsTranslationResult
    {
        $payload = $this->basePayload(
            instructions: 'Translate the news title and description into natural Japanese. Preserve factual meaning. Do not add unsupported facts. Keep names, URLs, company names, and technical terms accurate. Return only JSON that matches the schema.',
            input: [
                'phase' => 'translation',
                'title' => $item->title,
                'description' => $this->textSelector->bodyText($item),
                'source_url' => $item->source_url,
            ],
            schemaName: 'digestpipe_translation',
            schema: [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'translated_title' => ['type' => 'string'],
                    'translated_description' => ['type' => ['string', 'null']],
                ],
                'required' => ['translated_title', 'translated_description'],
            ],
        );

        $data = $this->requestStructuredJson($payload, $item, 'translation');

        $title = $data['translated_title'] ?? null;
        $description = $data['translated_description'] ?? null;

        if (! is_string($title) || trim($title) === '') {
            throw new AiProcessingException('OpenAI translation response did not include translated_title.');
        }

        if (! is_string($description)) {
            $description = null;
        }

        return new NewsTranslationResult(
            title: trim($title),
            description: $description === null ? null : trim($description),
        );
    }

    /**
     * News itemから日本語要約結果を生成します。
     */
    public function summarize(NewsItem $item): NewsSummaryResult
    {
        $payload = $this->basePayload(
            instructions: 'Produce a concise Japanese summary for a news digest API. Preserve the original meaning and do not include unsupported details. Return only JSON that matches the schema.',
            input: [
                'phase' => 'summary',
                'translated_title' => $item->translated_title,
                'translated_description' => $item->translated_description ?? $this->textSelector->bodyText($item),
                'source_url' => $item->source_url,
            ],
            schemaName: 'digestpipe_summary',
            schema: [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'summary' => ['type' => 'string'],
                ],
                'required' => ['summary'],
            ],
        );

        $data = $this->requestStructuredJson($payload, $item, 'summary');
        $summary = $data['summary'] ?? null;

        if (! is_string($summary) || trim($summary) === '') {
            throw new AiProcessingException('OpenAI summary response did not include summary.');
        }

        return new NewsSummaryResult(trim($summary));
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function basePayload(string $instructions, array $input, string $schemaName, array $schema): array
    {
        return [
            'model' => $this->model(),
            'store' => false,
            'instructions' => $instructions,
            'input' => json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => $schemaName,
                    'schema' => $schema,
                    'strict' => true,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function requestStructuredJson(array $payload, NewsItem $item, string $phase): array
    {
        $apiKey = $this->apiKey();
        $model = $this->model();

        Log::info('OpenAI news AI request started.', [
            'news_item_id' => $item->id,
            'source_key' => $item->source_key,
            'phase' => $phase,
            'model' => $model,
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
            Log::warning('OpenAI news AI request connection failed.', [
                'news_item_id' => $item->id,
                'source_key' => $item->source_key,
                'phase' => $phase,
                'model' => $model,
                'message' => $exception->getMessage(),
            ]);

            throw new AiProcessingException('OpenAI request failed: connection error.', previous: $exception);
        }

        if (! $response->successful()) {
            $message = $this->errorMessage($response);

            Log::warning('OpenAI news AI request failed.', [
                'news_item_id' => $item->id,
                'source_key' => $item->source_key,
                'phase' => $phase,
                'model' => $model,
                'http_status' => $response->status(),
                'message' => $message,
            ]);

            throw new AiProcessingException($message);
        }

        $jsonText = $this->extractOutputText($response);
        $data = $this->decodeJsonObject($jsonText);

        Log::info('OpenAI news AI request finished.', [
            'news_item_id' => $item->id,
            'source_key' => $item->source_key,
            'phase' => $phase,
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
            throw new AiProcessingException('OpenAI API key is not configured.');
        }

        return trim($value);
    }

    private function model(): string
    {
        $value = config('digestpipe.openai.model');

        if (! is_string($value) || trim($value) === '') {
            throw new AiProcessingException('OpenAI model is not configured.');
        }

        return trim($value);
    }

    private function requestTimeout(): int
    {
        $value = config('digestpipe.openai.request_timeout');

        return is_int($value) && $value > 0 ? $value : 60;
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
            return 'OpenAI request failed: ' . substr(trim($message), 0, 300);
        }

        if ($response->status() === 401) {
            return 'OpenAI request failed: authentication failed.';
        }

        if ($response->status() === 429) {
            return 'OpenAI request failed: rate limited.';
        }

        if ($response->serverError()) {
            return 'OpenAI request failed: server error.';
        }

        return 'OpenAI request failed with HTTP status ' . $response->status() . '.';
    }

    private function extractOutputText(Response $response): string
    {
        $outputText = $response->json('output_text');

        if (is_string($outputText) && trim($outputText) !== '') {
            return $outputText;
        }

        $output = $response->json('output');

        if (! is_array($output)) {
            throw new AiProcessingException('OpenAI response did not include output text.');
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

        throw new AiProcessingException('OpenAI response output text was empty.');
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(string $jsonText): array
    {
        try {
            $data = json_decode($jsonText, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new AiProcessingException('OpenAI response was not valid JSON.', previous: $exception);
        }

        if (! is_array($data)) {
            throw new AiProcessingException('OpenAI response JSON was not an object.');
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
