<?php

namespace App\Feeds;

/**
 * RSS/RDF フィードの情報をパースした結果
 */
class ParsedFeed
{
    /** @var list<FeedItem> 正規化した Feed Item のリスト */
    public readonly array $items;

    /** @var int 必須項目の不足などで無視したアイテム数 */
    public readonly int $failedItemCount;

    /**
     * Constructor
     *
     * @param list<FeedItem> $items
     * @param int $failedItemCount
     */
    public function __construct(array $items, int $failedItemCount)
    {
        $this->items = $items;
        $this->failedItemCount = $failedItemCount;
    }
}
