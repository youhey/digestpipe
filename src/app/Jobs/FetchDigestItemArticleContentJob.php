<?php

namespace App\Jobs;

use App\Articles\ArticleContentExtractionException;
use App\Articles\ArticleTextExtractor;
use App\Items\DigestItemWorkflow;
use App\Models\DigestItem;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Digest Itemの本文を取得
 */
class FetchDigestItemArticleContentJob implements ShouldQueue
{
    use Queueable;

    /** @var int 本文を取得するアイテム ID */
    public readonly int $digestItemId;

    /**
     * Constructor
     *
     * @param int $digestItemId
     */
    public function __construct(int $digestItemId)
    {
        $this->digestItemId = $digestItemId;
    }

    /**
     * Digest Itemの本文を取得して保存
     *
     * @param ArticleTextExtractor $extractor
     * @param DigestItemWorkflow|null $workflow
     *
     * @return void
     */
    public function handle(ArticleTextExtractor $extractor, ?DigestItemWorkflow $workflow = null): void
    {
        $workflow ??= app(DigestItemWorkflow::class);

        Log::info('Article content fetch job started.', [
            'digest_item_id' => $this->digestItemId,
        ]);

        $item = DigestItem::query()->find($this->digestItemId);

        if (! $item instanceof DigestItem) {
            Log::warning('Article content fetch job skipped missing item.', [
                'digest_item_id' => $this->digestItemId,
            ]);

            return;
        }

        if ($item->article_content_status === 'completed') {
            Log::info('Article content fetch job skipped completed item.', [
                'digest_item_id' => $item->id,
            ]);

            return;
        }

        if (! is_string($item->source_url) || trim($item->source_url) === '') {
            $this->markSkipped($item, 'Article URL is not available.');

            return;
        }

        $articleUrl = trim($item->source_url);

        $item->forceFill([
            'article_content_status' => 'processing',
            'article_content_started_at' => CarbonImmutable::now(),
            'article_content_error' => null,
        ])->save();

        Log::info('Article content status changed.', [
            'digest_item_id' => $item->id,
            'article_url' => $articleUrl,
            'article_content_status' => 'processing',
        ]);

        try {
            $response = $this->fetch($articleUrl);
        } catch (ConnectionException $exception) {
            $this->markFailed($item, 'Article fetch failed: connection error.');

            Log::warning('Article content fetch connection failed.', [
                'digest_item_id' => $item->id,
                'article_url' => $item->source_url,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        if (! $response->successful()) {
            $this->markFailed($item, 'Article fetch failed with HTTP status ' . $response->status() . '.');

            Log::warning('Article content fetch HTTP failed.', [
                'digest_item_id' => $item->id,
                'article_url' => $item->source_url,
                'http_status' => $response->status(),
            ]);

            return;
        }

        $contentType = $response->header('Content-Type');

        if (! $this->isHtmlContentType($contentType)) {
            $this->markSkipped($item, 'Article response was not HTML.');

            Log::info('Article content fetch skipped non-HTML response.', [
                'digest_item_id' => $item->id,
                'article_url' => $item->source_url,
                'content_type' => $contentType,
            ]);

            return;
        }

        $body = $response->body();

        if ($body === '') {
            $this->markSkipped($item, 'Article response body was empty.');

            return;
        }

        if (strlen($body) > $this->maxBytes()) {
            $this->markSkipped($item, 'Article response body was too large.');

            return;
        }

        try {
            $content = $extractor->extract($body);
        } catch (ArticleContentExtractionException $exception) {
            $this->markSkipped($item, $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            $this->markFailed($item, 'Article text extraction failed.');

            Log::error('Article content extraction failed unexpectedly.', [
                'digest_item_id' => $item->id,
                'article_url' => $item->source_url,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        $item->forceFill([
            'article_content_status' => 'completed',
            'article_content_text' => $content->text,
            'article_content_fetched_at' => CarbonImmutable::now(),
            'article_content_error' => null,
        ])->save();

        Log::info('Article content fetch job finished.', [
            'digest_item_id' => $item->id,
            'article_url' => $item->source_url,
            'content_type' => $contentType,
            'extracted_character_count' => $content->characterCount,
            'article_content_status' => 'completed',
        ]);

        $workflow->dispatchAnalysisIfReady($item);
    }

    private function fetch(string $url): Response
    {
        return Http::timeout($this->timeout())
            ->withUserAgent($this->userAgent())
            ->withOptions([
                'allow_redirects' => [
                    'max' => 3,
                ],
            ])
            ->get($url);
    }

    private function markSkipped(DigestItem $item, string $message): void
    {
        $item->forceFill([
            'article_content_status' => 'skipped',
            'article_content_skipped_at' => CarbonImmutable::now(),
            'article_content_error' => self::shortMessage($message),
        ])->save();

        Log::info('Article content fetch skipped.', [
            'digest_item_id' => $item->id,
            'article_url' => $item->source_url,
            'reason' => self::shortMessage($message),
        ]);

        app(DigestItemWorkflow::class)->dispatchAnalysisIfReady($item);
    }

    private function markFailed(DigestItem $item, string $message): void
    {
        $item->forceFill([
            'article_content_status' => 'failed',
            'article_content_failed_at' => CarbonImmutable::now(),
            'article_content_error' => self::shortMessage($message),
        ])->save();

        Log::warning('Article content fetch failed.', [
            'digest_item_id' => $item->id,
            'article_url' => $item->source_url,
            'reason' => self::shortMessage($message),
        ]);
    }

    private function isHtmlContentType(?string $contentType): bool
    {
        if ($contentType === null || trim($contentType) === '') {
            return true;
        }

        return str_contains(strtolower($contentType), 'text/html');
    }

    private function timeout(): int
    {
        $value = config('digestpipe.content.fetch_timeout');

        return is_int($value) && $value > 0 ? $value : 15;
    }

    private function maxBytes(): int
    {
        $value = config('digestpipe.content.max_bytes');

        return is_int($value) && $value > 0 ? $value : 1048576;
    }

    private function userAgent(): string
    {
        $value = config('digestpipe.content.user_agent');

        return is_string($value) && trim($value) !== '' ? trim($value) : 'digestpipe/0.1 (+structured digest pipeline)';
    }

    private static function shortMessage(string $message): string
    {
        return substr($message, 0, 500);
    }
}
