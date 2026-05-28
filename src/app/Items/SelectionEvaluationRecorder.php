<?php

namespace App\Items;

use App\Models\DigestItem;
use App\Models\SelectionEvaluation;
use Carbon\CarbonImmutable;

/**
 * Digest Item の selection 評価履歴を append-only で記録します。
 */
class SelectionEvaluationRecorder
{
    /**
     * Selection 評価結果を履歴テーブルへ保存します。
     */
    public function record(DigestItem $item, DigestItemSelectionResult $result, string $phase, CarbonImmutable $evaluatedAt): SelectionEvaluation
    {
        return SelectionEvaluation::query()->create([
            'digest_item_id' => $item->id,
            'source_key' => $item->source_key,
            'phase' => $phase,
            'status' => $result->status,
            'score' => $result->score,
            'reason' => $result->reason,
            'matched_positive_keywords' => $result->matchedGoodKeywords,
            'matched_negative_keywords' => $result->matchedBadKeywords,
            'input_summary' => $this->inputSummary($item),
            'selection_config_summary' => $this->selectionConfigSummary(),
            'evaluated_at' => $evaluatedAt,
        ]);
    }

    /**
     * 評価時に使えた入力の軽量 metadata を返します。
     *
     * @return array{title_present: bool, excerpt_present: bool, article_content_present: bool, title_length: int, excerpt_length: int, article_content_length: int}
     */
    private function inputSummary(DigestItem $item): array
    {
        return [
            'title_present' => $this->present($item->title),
            'excerpt_present' => $this->present($item->excerpt),
            'article_content_present' => $this->present($item->article_content_text),
            'title_length' => $this->length($item->title),
            'excerpt_length' => $this->length($item->excerpt),
            'article_content_length' => $this->length($item->article_content_text),
        ];
    }

    /**
     * Selection 評価に使う threshold 設定の要約を返します。
     *
     * @return array{analysis_threshold: int|null, skip_threshold: int|null, default_score: int|null}
     */
    private function selectionConfigSummary(): array
    {
        return [
            'analysis_threshold' => $this->integerConfig('analysis_threshold'),
            'skip_threshold' => $this->integerConfig('skip_threshold'),
            'default_score' => $this->integerConfig('default_score'),
        ];
    }

    private function present(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    private function length(?string $value): int
    {
        if ($value === null) {
            return 0;
        }

        return mb_strlen($value);
    }

    private function integerConfig(string $key): ?int
    {
        $value = config('digestpipe.selection.' . $key);

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }
}
