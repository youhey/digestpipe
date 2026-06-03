<?php

namespace App\Feeds;

/**
 * RSS フィード情報源から Digest Item を取り込んだ集計結果
 */
class IngestFeedItemsResult
{
    /** @var int 新規作成または dry-run で作成予定のアイテム数 */
    public readonly int $createdCount;

    /** @var int スキップした既存アイテムの件数 */
    public readonly int $skippedDuplicateCount;

    /** @var list<int> 新規作成した Digest Item ID */
    public readonly array $createdDigestItemIds;

    /**
     * Constructor
     *
     * @param int $createdCount
     * @param int $skippedDuplicateCount
     * @param list<int> $createdDigestItemIds
     */
    public function __construct(int $createdCount, int $skippedDuplicateCount, array $createdDigestItemIds = [])
    {
        $this->createdCount = $createdCount;
        $this->skippedDuplicateCount = $skippedDuplicateCount;
        $this->createdDigestItemIds = $createdDigestItemIds;
    }
}
