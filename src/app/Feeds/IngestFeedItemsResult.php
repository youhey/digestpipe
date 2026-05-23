<?php

namespace App\Feeds;

/**
 * Feed item保存処理の集計結果です。
 */
class IngestFeedItemsResult
{
    /** 新規作成した、またはdry-runで作成予定だったitem数です。 */
    public readonly int $createdCount;

    /** 既存itemとしてskipした件数です。 */
    public readonly int $skippedDuplicateCount;

    /**
     * 保存処理結果を作成します。
     */
    public function __construct(int $createdCount, int $skippedDuplicateCount)
    {
        $this->createdCount = $createdCount;
        $this->skippedDuplicateCount = $skippedDuplicateCount;
    }
}
