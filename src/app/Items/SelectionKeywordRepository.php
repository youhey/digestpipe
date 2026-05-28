<?php

namespace App\Items;

use Illuminate\Support\Facades\DB;

/**
 * Selection keyword master data を DB から読み込む
 */
class SelectionKeywordRepository
{
    /**
     * 有効な positive keyword score map を返す
     *
     * @return array<string, int>
     */
    public function positiveKeywords(): array
    {
        return $this->keywordsByType('positive');
    }

    /**
     * 有効な positive keyword rule を返す
     *
     * @return list<SelectionKeywordRule>
     */
    public function positiveKeywordRules(): array
    {
        return $this->keywordRulesByType('positive');
    }

    /**
     * 有効な negative keyword score map を返す
     *
     * @return array<string, int>
     */
    public function negativeKeywords(): array
    {
        return $this->keywordsByType('negative');
    }

    /**
     * 有効な negative keyword rule を返す
     *
     * @return list<SelectionKeywordRule>
     */
    public function negativeKeywordRules(): array
    {
        return $this->keywordRulesByType('negative');
    }

    /**
     * @return array<string, int>
     */
    private function keywordsByType(string $type): array
    {
        $keywords = [];

        foreach (DB::table('selection_keywords')
            ->where('enabled', true)
            ->where('type', $type)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['keyword', 'score']) as $row) {
            if (is_string($row->keyword) && (is_int($row->score) || is_numeric($row->score))) {
                $keywords[$row->keyword] = (int) $row->score;
            }
        }

        return $keywords;
    }

    /**
     * @return list<SelectionKeywordRule>
     */
    private function keywordRulesByType(string $type): array
    {
        $rules = [];

        foreach (DB::table('selection_keywords')
            ->where('enabled', true)
            ->where('type', $type)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['keyword', 'score', 'match_mode']) as $row) {
            if (
                is_string($row->keyword)
                && (is_int($row->score) || is_numeric($row->score))
                && is_string($row->match_mode)
            ) {
                $rules[] = new SelectionKeywordRule($row->keyword, (int) $row->score, $row->match_mode);
            }
        }

        return $rules;
    }
}
