<?php

namespace App\Items;

use App\Models\DigestItem;

/**
 * Digest Itemの現在の状態から次に必要な処理を決定する
 */
class DigestItemProcessingPlanner
{
    /**
     * Digest Itemの現在の状態から、アイテム1つごと1つだけ次に必要な処理を返す
     *
     * @param DigestItem $item
     * @param string|null $stage
     *
     * @return DigestItemProcessingPlan
     */
    public function plan(DigestItem $item, ?string $stage = null): DigestItemProcessingPlan
    {
        if ($item->article_content_status === 'pending') {
            return DigestItemProcessingPlan::contentFetch('article_content_pending');
        }

        if ($item->article_content_status === 'queued' || $item->article_content_status === 'processing') {
            return DigestItemProcessingPlan::none('article_content_' . $item->article_content_status);
        }

        if ($item->article_content_status === 'failed') {
            return DigestItemProcessingPlan::none('article_content_failed');
        }

        if ($item->article_content_status !== 'completed' && $item->article_content_status !== 'skipped') {
            return DigestItemProcessingPlan::none('article_content_unusable');
        }

        if ($item->analysis_status === 'pending') {
            $reason = $item->article_content_status === 'skipped'
                ? 'article_content_skipped_analysis_pending'
                : 'article_content_completed_analysis_pending';

            return DigestItemProcessingPlan::analysis($reason);
        }

        if ($item->analysis_status === 'queued' || $item->analysis_status === 'processing') {
            return DigestItemProcessingPlan::none('analysis_' . $item->analysis_status);
        }

        return DigestItemProcessingPlan::none('analysis_' . $item->analysis_status);
    }
}
