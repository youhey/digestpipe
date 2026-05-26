<?php

namespace App\Jobs;

use App\Articles\ArticleTextExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * 旧 queued payload との互換性のため Digest Item 本文取得ジョブへ委譲
 */
class FetchNewsItemArticleContentJob implements ShouldQueue
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
     * @param ArticleTextExtractor $extractor
     *
     * @return void
     */
    public function handle(ArticleTextExtractor $extractor): void
    {
        (new FetchDigestItemArticleContentJob($this->newsItemId))->handle($extractor);
    }
}
