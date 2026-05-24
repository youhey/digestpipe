<?php

namespace App\Items;

use App\Models\NewsItem;

/**
 * News itemの現在状態から次にdispatch可能な処理を決定します。
 */
class NewsItemProcessingPlanner
{
    /**
     * 現在状態に基づき、1 itemにつき1つだけ次の処理を返します。
     */
    public function plan(NewsItem $item): NewsItemProcessingPlan
    {
        if ($item->summary_status === 'completed') {
            return NewsItemProcessingPlan::none('summary_completed');
        }

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

        if ($item->translation_status === 'pending') {
            $reason = $item->article_content_status === 'skipped'
                ? 'article_content_skipped_translation_pending'
                : 'article_content_completed_translation_pending';

            return NewsItemProcessingPlan::translation($reason);
        }

        if ($item->translation_status === 'queued' || $item->translation_status === 'processing') {
            return NewsItemProcessingPlan::none('translation_' . $item->translation_status);
        }

        if ($item->translation_status !== 'completed') {
            return NewsItemProcessingPlan::none('translation_' . $item->translation_status);
        }

        if ($item->summary_status === 'pending') {
            return NewsItemProcessingPlan::summary('translation_completed_summary_pending');
        }

        return NewsItemProcessingPlan::none('summary_' . $item->summary_status);
    }
}
