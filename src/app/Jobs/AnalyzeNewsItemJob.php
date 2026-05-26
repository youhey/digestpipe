<?php

namespace App\Jobs;

use App\Analysis\ArticleAnalyzer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * 旧 queued payload との互換性のため Digest Item 分析ジョブへ委譲
 */
class AnalyzeNewsItemJob implements ShouldQueue
{
    use Queueable;

    /** @var int 旧ジョブ payload が保持するアイテム ID */
    public readonly int $newsItemId;

    /**
     * Constructor
     *
     * @param int $newsItemId
     */
    public function __construct(int $newsItemId)
    {
        $this->newsItemId = $newsItemId;
    }

    /**
     * 旧ジョブ名で復元された payload を新しい Digest Item ジョブで処理する
     *
     * @param ArticleAnalyzer $analyzer
     *
     * @return void
     */
    public function handle(ArticleAnalyzer $analyzer): void
    {
        (new AnalyzeDigestItemJob($this->newsItemId))->handle($analyzer);
    }
}
