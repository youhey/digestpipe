<?php

namespace App\Feeds;

/**
 * RSS フィード情報源からニュース取得した集計結果
 */
class IngestFeedItemsResult
{
    /** @var int 新規作成または dry-run で作成予定のアイテム数 */
    public readonly int $createdCount;

    /** @var int スキップした既存アイテムの件数 */
    public readonly int $skippedDuplicateCount;

    /**
     * Constructor
     *
     * @param int $createdCount
     * @param int $skippedDuplicateCount
     */
    public function __construct(int $createdCount, int $skippedDuplicateCount)
    {
        $this->createdCount = $createdCount;
        $this->skippedDuplicateCount = $skippedDuplicateCount;
    }
}
