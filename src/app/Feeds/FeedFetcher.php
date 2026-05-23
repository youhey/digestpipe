<?php

namespace App\Feeds;

use Illuminate\Support\Facades\Http;

/**
 * RSS feed sourceからXML payloadをHTTPで取得します。
 */
class FeedFetcher
{
    /**
     * 指定されたfeed source URLへHTTP requestを送り、statusとbodyを返します。
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
