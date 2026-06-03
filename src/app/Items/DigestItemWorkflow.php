<?php

namespace App\Items;

use App\Jobs\AnalyzeDigestItemJob;
use App\Jobs\FetchDigestItemArticleContentJob;
use App\Models\DigestItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Digest Item の処理状態遷移と Queue dispatch を集約します。
 */
class DigestItemWorkflow
{
    private readonly DigestItemSelector $selector;

    private readonly SelectionEvaluationRecorder $selectionEvaluations;

    /**
     * Constructor
     */
    public function __construct(DigestItemSelector $selector, SelectionEvaluationRecorder $selectionEvaluations)
    {
        $this->selector = $selector;
        $this->selectionEvaluations = $selectionEvaluations;
    }

    /**
     * 新規 Digest Item の本文取得 Job を必要に応じて dispatch します。
     */
    public function dispatchArticleFetchIfReady(DigestItem $item): bool
    {
        if (! $this->selectionAllowsArticleFetch($item)) {
            return false;
        }

        $queuedAt = CarbonImmutable::now();
        $updated = DigestItem::query()
            ->whereKey($item->id)
            ->where('article_content_status', 'pending')
            ->update([
                'article_content_status' => 'queued',
                'article_content_queued_at' => $queuedAt,
                'updated_at' => $queuedAt,
            ]);

        if ($updated !== 1) {
            return false;
        }

        FetchDigestItemArticleContentJob::dispatch($item->id);

        Log::info('Digest item article fetch job queued by workflow.', [
            'digest_item_id' => $item->id,
            'source_key' => $item->source_key,
        ]);

        return true;
    }

    /**
     * 本文取得後に分析 Job を必要に応じて dispatch します。
     */
    public function dispatchAnalysisIfReady(DigestItem $item): bool
    {
        $item->refresh();

        if (! in_array($item->article_content_status, ['completed', 'skipped'], true)) {
            return false;
        }

        if ($item->analysis_status !== 'pending') {
            return false;
        }

        if (! $this->hasUsableAnalysisInput($item)) {
            return false;
        }

        if (! $this->selectionAllowsAnalysis($item)) {
            return false;
        }

        $queuedAt = CarbonImmutable::now();
        $query = DigestItem::query()
            ->whereKey($item->id)
            ->where('analysis_status', 'pending')
            ->where(static function (Builder $query): void {
                $query
                    ->where('article_content_status', 'completed')
                    ->orWhere('article_content_status', 'skipped');
            });
        $updated = $query->update([
            'analysis_status' => 'queued',
            'analysis_queued_at' => $queuedAt,
            'updated_at' => $queuedAt,
        ]);

        if ($updated !== 1) {
            return false;
        }

        AnalyzeDigestItemJob::dispatch($item->id);

        Log::info('Digest item analysis job queued by workflow.', [
            'digest_item_id' => $item->id,
            'source_key' => $item->source_key,
        ]);

        return true;
    }

    private function selectionAllowsArticleFetch(DigestItem $item): bool
    {
        if (! $this->selector->enabled()) {
            return true;
        }

        if ($item->selection_status === 'skipped') {
            return false;
        }

        if ($item->selection_status === 'selected' || $item->selection_status === 'needs_content') {
            return true;
        }

        $result = $this->selector->evaluatePreContent($item);
        $this->storeSelectionResult($item, $result, 'pre_content');

        return $result->status !== 'skipped';
    }

    private function selectionAllowsAnalysis(DigestItem $item): bool
    {
        if (! $this->selector->enabled()) {
            return true;
        }

        if ($item->selection_status === 'selected') {
            return true;
        }

        if ($item->selection_status === 'skipped') {
            return false;
        }

        $result = $this->selector->evaluatePostContent($item);
        $this->storeSelectionResult($item, $result, 'post_content');

        return $result->status === 'selected';
    }

    private function storeSelectionResult(DigestItem $item, DigestItemSelectionResult $result, string $phase): void
    {
        $evaluatedAt = CarbonImmutable::now();

        DB::transaction(function () use ($item, $result, $phase, $evaluatedAt): void {
            $item->forceFill([
                'selection_status' => $result->status,
                'selection_score' => $result->score,
                'selection_reason' => $result->reason,
                'selection_result' => $result->toArray(),
                'selection_evaluated_at' => $evaluatedAt,
            ])->save();

            $this->selectionEvaluations->record($item, $result, $phase, $evaluatedAt);
        });
    }

    private function hasUsableAnalysisInput(DigestItem $item): bool
    {
        foreach ([$item->article_content_text, $item->excerpt, $item->title] as $value) {
            if (is_string($value) && trim(strip_tags($value)) !== '') {
                return true;
            }
        }

        return false;
    }
}
