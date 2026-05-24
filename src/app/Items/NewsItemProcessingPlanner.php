<?php

namespace App\Items;

use App\Models\NewsItem;

/**
 * ニュース記事アイテムの現在の状態から次に必要な処理を決定する
 */
class NewsItemProcessingPlanner
{
    /**
     * ニュース記事の現在の状態から、アイテム1つごと1つだけ次に必要な処理を返す
     *
     * @param NewsItem $item
     * @param string|null $stage
     *
     * @return NewsItemProcessingPlan
     */
    public function plan(NewsItem $item, ?string $stage = null): NewsItemProcessingPlan
    {
        if ($item->article_content_status === 'pending') {
            return NewsItemProcessingPlan::contentFetch('article_content_pending');
        }

        if ($item->article_content_status === 'queued' || $item->article_content_status === 'processing') {
            return NewsItemProcessingPlan::none('article_content_' . $item->article_content_status);
        }

        if ($item->article_content_status === 'failed') {
            return NewsItemProcessingPlan::none('article_content_failed');
        }

        if ($item->article_content_status !== 'completed' && $item->article_content_status !== 'skipped') {
            return NewsItemProcessingPlan::none('article_content_unusable');
        }

        if ($item->analysis_status === 'pending') {
            $reason = $item->article_content_status === 'skipped'
                ? 'article_content_skipped_analysis_pending'
                : 'article_content_completed_analysis_pending';

            return NewsItemProcessingPlan::analysis($reason);
        }

        if ($item->analysis_status === 'queued' || $item->analysis_status === 'processing') {
            return NewsItemProcessingPlan::none('analysis_' . $item->analysis_status);
        }

        return NewsItemProcessingPlan::none('analysis_' . $item->analysis_status);
    }
}
