<?php

namespace App\Items;

/**
 * News item processing orchestrationの判定結果です。
 */
class NewsItemProcessingPlan
{
    /** Dispatchすべきstageです。 */
    public readonly ?string $stage;

    /** Dispatchするjob class名です。 */
    public readonly ?string $jobClass;

    /** queuedへ更新するstatus field名です。 */
    public readonly ?string $statusField;

    /** 判定理由を表す短い文字列です。 */
    public readonly string $reason;

    /**
     * 判定結果を作成します。
     */
    public function __construct(?string $stage, ?string $jobClass, ?string $statusField, string $reason)
    {
        $this->stage = $stage;
        $this->jobClass = $jobClass;
        $this->statusField = $statusField;
        $this->reason = $reason;
    }

    /**
     * Dispatch対象があるかどうかを返します。
     */
    public function shouldDispatch(): bool
    {
        return $this->stage !== null && $this->jobClass !== null && $this->statusField !== null;
    }

    /**
     * Article content fetch jobの判定結果を返します。
     */
    public static function contentFetch(string $reason): self
    {
        return new self('content', 'App\Jobs\FetchNewsItemArticleContentJob', 'article_content_status', $reason);
    }

    /**
     * Translation jobの判定結果を返します。
     */
    public static function translation(string $reason): self
    {
        return new self('translation', 'App\Jobs\TranslateNewsItemJob', 'translation_status', $reason);
    }

    /**
     * Summary jobの判定結果を返します。
     */
    public static function summary(string $reason): self
    {
        return new self('summary', 'App\Jobs\SummarizeNewsItemJob', 'summary_status', $reason);
    }

    /**
     * Dispatch対象がない判定結果を返します。
     */
    public static function none(string $reason): self
    {
        return new self(null, null, null, $reason);
    }
}
