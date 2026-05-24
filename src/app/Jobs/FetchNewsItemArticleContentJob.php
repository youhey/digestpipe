<?php

namespace App\Jobs;

use App\Articles\ArticleContentExtractionException;
use App\Articles\ArticleTextExtractor;
use App\Models\NewsItem;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ニュース記事アイテムの本文を取得
 */
class FetchNewsItemArticleContentJob implements ShouldQueue
{
    use Queueable;

    /** @var int 本文を取得するアイテム ID */
    public readonly int $newsItemId;

    /**
     * Constructor
     *
     * @param int $newsItemId
     */
    public function __construct(int $newsItemId)
    {
        $this->newsItemId = $newsItemId;
    }

    /**
     * ニュース記事アイテムの本文を取得して保存
     *
     * @param ArticleTextExtractor $extractor
     *
     * @return void
     */
    public function handle(ArticleTextExtractor $extractor): void
    {
        Log::info('Article content fetch job started.', [
            'news_item_id' => $this->newsItemId,
        ]);

        $item = NewsItem::query()->find($this->newsItemId);

        if (! $item instanceof NewsItem) {
            Log::warning('Article content fetch job skipped missing item.', [
                'news_item_id' => $this->newsItemId,
            ]);

            return;
        }

        if ($item->article_content_status === 'completed') {
            Log::info('Article content fetch job skipped completed item.', [
                'news_item_id' => $item->id,
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
            'article_content_error' => null,
        ])->save();

        Log::info('Article content status changed.', [
            'news_item_id' => $item->id,
            'article_url' => $articleUrl,
            'article_content_status' => 'processing',
        ]);

        try {
            $response = $this->fetch($articleUrl);
        } catch (ConnectionException $exception) {
            $this->markFailed($item, 'Article fetch failed: connection error.');

            Log::warning('Article content fetch connection failed.', [
                'news_item_id' => $item->id,
                'article_url' => $item->source_url,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        if (! $response->successful()) {
            $this->markFailed($item, 'Article fetch failed with HTTP status ' . $response->status() . '.');

            Log::warning('Article content fetch HTTP failed.', [
                'news_item_id' => $item->id,
                'article_url' => $item->source_url,
                'http_status' => $response->status(),
            ]);

            return;
        }

        $contentType = $response->header('Content-Type');

        if (! $this->isHtmlContentType($contentType)) {
            $this->markSkipped($item, 'Article response was not HTML.');

            Log::info('Article content fetch skipped non-HTML response.', [
                'news_item_id' => $item->id,
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
                'news_item_id' => $item->id,
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
            'news_item_id' => $item->id,
            'article_url' => $item->source_url,
            'content_type' => $contentType,
            'extracted_character_count' => $content->characterCount,
            'article_content_status' => 'completed',
        ]);
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

    private function markSkipped(NewsItem $item, string $message): void
    {
        $item->forceFill([
            'article_content_status' => 'skipped',
            'article_content_error' => self::shortMessage($message),
        ])->save();

        Log::info('Article content fetch skipped.', [
            'news_item_id' => $item->id,
            'article_url' => $item->source_url,
            'reason' => self::shortMessage($message),
        ]);
    }

    private function markFailed(NewsItem $item, string $message): void
    {
        $item->forceFill([
            'article_content_status' => 'failed',
            'article_content_error' => self::shortMessage($message),
        ])->save();

        Log::warning('Article content fetch failed.', [
            'news_item_id' => $item->id,
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

        return is_string($value) && trim($value) !== '' ? trim($value) : 'digestpipe/0.1 (+personal news summarizer)';
    }

    private static function shortMessage(string $message): string
    {
        return substr($message, 0, 500);
    }
}
