<?php

namespace App\Feeds;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * RSS フィードの情報源から XML Payload を取得
 */
class FeedFetcher
{
    /**
     * 指定された RSS フィードの URL からコンテンツを HTTP ダウンロード
     *
     * @param FeedSource $source 情報源
     *
     * @return FetchedFeed ダウンロードしたコンテンツの Status と Body
     *
     * @throws ConnectionException
     */
    public function fetch(FeedSource $source): FetchedFeed
    {
        $response = Http::timeout(15)
            ->withUserAgent('digestpipe-feed-fetcher/1.0')
            ->get($source->url);

        return new FetchedFeed(
            statusCode: $response->status(),
            body: $response->body(),
            successful: $response->successful(),
        );
    }
}
