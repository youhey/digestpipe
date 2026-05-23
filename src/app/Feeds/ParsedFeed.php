<?php

namespace App\Feeds;

/**
 * RSS/RDF parse結果です。
 */
class ParsedFeed
{
    /**
     * 正規化できたfeed item一覧です。
     *
     * @var list<FeedItem>
     */
    public readonly array $items;

    /** 必須項目不足などでskipしたitem数です。 */
    public readonly int $failedItemCount;

    /**
     * Parse結果を作成します。
     *
     * @param list<FeedItem> $items
     */
    public function __construct(array $items, int $failedItemCount)
    {
        $this->items = $items;
        $this->failedItemCount = $failedItemCount;
    }
}
