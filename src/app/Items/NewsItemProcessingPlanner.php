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
    public function plan(NewsItem $item, ?string $stage = null): NewsItemProcessingPlan
    {
        if ($stage === 'translation') {
            return $this->legacyTranslationPlan($item);
        }

        if ($stage === 'summary') {
            return $this->legacySummaryPlan($item);
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

    private function legacyTranslationPlan(NewsItem $item): NewsItemProcessingPlan
    {
        if ($item->article_content_status !== 'completed' && $item->article_content_status !== 'skipped') {
            return NewsItemProcessingPlan::none('article_content_not_ready_for_translation');
        }

        if ($item->translation_status === 'pending') {
            $reason = $item->article_content_status === 'skipped'
                ? 'article_content_skipped_translation_pending'
                : 'article_content_completed_translation_pending';

            return NewsItemProcessingPlan::translation($reason);
        }

        return NewsItemProcessingPlan::none('translation_' . $item->translation_status);
    }

    private function legacySummaryPlan(NewsItem $item): NewsItemProcessingPlan
    {
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
