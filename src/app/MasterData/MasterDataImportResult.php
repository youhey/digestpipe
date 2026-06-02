<?php

namespace App\MasterData;

/**
 * Master data import の作成・更新件数を保持します。
 */
class MasterDataImportResult
{
    /** @var int 新規作成した record 数 */
    public int $created;

    /** @var int 更新した record 数 */
    public int $updated;

    /** @var int 空行として無視した row 数 */
    public int $skipped;

    /**
     * Constructor
     *
     * @param int $created
     * @param int $updated
     * @param int $skipped
     */
    public function __construct(int $created = 0, int $updated = 0, int $skipped = 0)
    {
        $this->created = $created;
        $this->updated = $updated;
        $this->skipped = $skipped;
    }

    /**
     * UI notification 用の短い summary を返します。
     */
    public function summary(): string
    {
        return sprintf('created: %d, updated: %d, skipped: %d', $this->created, $this->updated, $this->skipped);
    }
}
