<?php

namespace App\Items;

use App\Jobs\AnalyzeNewsItemJob;
use App\Jobs\FetchNewsItemArticleContentJob;
use App\Jobs\SummarizeNewsItemJob;
use App\Jobs\TranslateNewsItemJob;

/**
 * ニュース記事アイテムのプロセス間の連携状態
 *
 * 次に処理するべきプロセスを判定するための状態
 */
class NewsItemProcessingPlan
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
     * 次にニュース記事の本文取得を必要としている状態を生成して返す
     *
     * @param string $reason
     *
     * @return self
     */
    public static function contentFetch(string $reason): self
    {
        return new self('content', FetchNewsItemArticleContentJob::class, 'article_content_status', $reason);
    }

    /**
     * 次にニュース記事の分析を必要としている状態を生成して返す
     *
     * @param string $reason
     *
     * @return self
     */
    public static function analysis(string $reason): self
    {
        return new self('analysis', AnalyzeNewsItemJob::class, 'analysis_status', $reason);
    }

    /**
     * 次にニュース記事の翻訳を必要としている状態を生成して返す
     *
     * @param string $reason
     *
     * @return self
     */
    public static function translation(string $reason): self
    {
        return new self('translation', TranslateNewsItemJob::class, 'translation_status', $reason);
    }

    /**
     * 次にニュース記事の要約を必要としている状態を生成して返す
     *
     * @param string $reason
     *
     * @return self
     */
    public static function summary(string $reason): self
    {
        return new self('summary', SummarizeNewsItemJob::class, 'summary_status', $reason);
    }

    /**
     * ニュース記事には次に必要な処理がない状態を生成して返す
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
