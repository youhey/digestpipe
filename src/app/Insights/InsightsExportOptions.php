<?php

namespace App\Insights;

/**
 * Insights export の入力条件を保持します。
 */
class InsightsExportOptions
{
    /** @var int 対象期間の日数 */
    public int $days;

    /** @var string|null Source Key filter */
    public ?string $source;

    /** @var int selected/skipped sample item の上限 */
    public int $sampleLimit;

    /** @var int keyword aggregation の上限 */
    public int $keywordLimit;

    /** @var string export format */
    public string $format;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->days = 7;
        $this->source = null;
        $this->sampleLimit = 20;
        $this->keywordLimit = 20;
        $this->format = 'markdown';
    }

    /**
     * 指定値から export options を生成します。
     */
    public static function make(
        int $days = 7,
        ?string $source = null,
        int $sampleLimit = 20,
        int $keywordLimit = 20,
        string $format = 'markdown',
    ): self {
        $options = new self();
        $options->days = $days;
        $options->source = $source;
        $options->sampleLimit = $sampleLimit;
        $options->keywordLimit = $keywordLimit;
        $options->format = $format;

        return $options;
    }
}
