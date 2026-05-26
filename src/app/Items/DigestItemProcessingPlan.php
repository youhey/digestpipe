<?php

namespace App\Items;

use App\Jobs\AnalyzeDigestItemJob;
use App\Jobs\FetchDigestItemArticleContentJob;

/**
 * Digest Itemのプロセス間の連携状態
 *
 * 次に処理するべきプロセスを判定するための状態
 */
class DigestItemProcessingPlan
{
    /** @var string|null 処理を割り当てるステージ */
    public readonly ?string $stage;

    /** @var string|null 処理を割り当てるジョブ・クラスの名前 */
    public readonly ?string $jobClass;

    /** @var string|null `queued` へ更新する Status Field 名。 */
    public readonly ?string $statusField;

    /** @var string 判定理由を表現する短い文字列 */
    public readonly string $reason;

    /**
     * Constructor
     *
     * @param string|null $stage
     * @param string|null $jobClass
     * @param string|null $statusField
     * @param string $reason
     */
    public function __construct(?string $stage, ?string $jobClass, ?string $statusField, string $reason)
    {
        $this->stage = $stage;
        $this->jobClass = $jobClass;
        $this->statusField = $statusField;
        $this->reason = $reason;
    }

    /**
     * 割り当てる処理が存在すれば `TRUE` を、なければ `FALSE` を返す
     *
     * @return bool
     */
    public function shouldDispatch(): bool
    {
        return $this->stage !== null && $this->jobClass !== null && $this->statusField !== null;
    }

    /**
     * 次にDigest Itemの本文取得を必要としている状態を生成して返す
     *
     * @param string $reason
     *
     * @return self
     */
    public static function contentFetch(string $reason): self
    {
        return new self('content', FetchDigestItemArticleContentJob::class, 'article_content_status', $reason);
    }

    /**
     * 次にDigest Itemの分析を必要としている状態を生成して返す
     *
     * @param string $reason
     *
     * @return self
     */
    public static function analysis(string $reason): self
    {
        return new self('analysis', AnalyzeDigestItemJob::class, 'analysis_status', $reason);
    }

    /**
     * Digest Itemには次に必要な処理がない状態を生成して返す
     *
     * @param string $reason
     *
     * @return self
     */
    public static function none(string $reason): self
    {
        return new self(null, null, null, $reason);
    }
}
