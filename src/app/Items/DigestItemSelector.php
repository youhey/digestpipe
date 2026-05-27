<?php

namespace App\Items;

use App\Models\DigestItem;
use UnexpectedValueException;

/**
 * Digest Itemを本文取得前後の2段階で選別する
 */
class DigestItemSelector
{
    private readonly SelectionKeywordRepository $keywords;

    /**
     * Constructor
     *
     * @param SelectionKeywordRepository $keywords
     */
    public function __construct(SelectionKeywordRepository $keywords)
    {
        $this->keywords = $keywords;
    }

    /**
     * Digest Itemを通常の最終 selection として評価する
     *
     * @param DigestItem $item
     *
     * @return DigestItemSelectionResult
     */
    public function evaluate(DigestItem $item): DigestItemSelectionResult
    {
        return $this->evaluatePostContent($item);
    }

    /**
     * 本文取得前の title / excerpt で保守的な selection 結果を返す
     *
     * @param DigestItem $item
     *
     * @return DigestItemSelectionResult
     */
    public function evaluatePreContent(DigestItem $item): DigestItemSelectionResult
    {
        $evaluation = $this->evaluateText($item->title . "\n" . ($item->excerpt ?? ''));

        if ($evaluation->score <= $this->integerConfig('skip_threshold')) {
            return new DigestItemSelectionResult(
                $evaluation->score,
                'skipped',
                $evaluation->matchedGoodKeywords,
                $evaluation->matchedBadKeywords,
                'below_skip_threshold',
            );
        }

        return new DigestItemSelectionResult(
            $evaluation->score,
            'needs_content',
            $evaluation->matchedGoodKeywords,
            $evaluation->matchedBadKeywords,
            'pre_content_selection_deferred',
        );
    }

    /**
     * 本文取得後の title / excerpt / article content で最終 selection 結果を返す
     *
     * @param DigestItem $item
     *
     * @return DigestItemSelectionResult
     */
    public function evaluatePostContent(DigestItem $item): DigestItemSelectionResult
    {
        $text = implode("\n", array_filter([
            $item->title,
            $item->excerpt,
            $item->article_content_text,
        ], static fn (?string $value): bool => $value !== null && $value !== ''));
        $evaluation = $this->evaluateText($text);

        if ($evaluation->score >= $this->integerConfig('analysis_threshold')) {
            return new DigestItemSelectionResult(
                $evaluation->score,
                'selected',
                $evaluation->matchedGoodKeywords,
                $evaluation->matchedBadKeywords,
                'above_analysis_threshold',
            );
        }

        return new DigestItemSelectionResult(
            $evaluation->score,
            'skipped',
            $evaluation->matchedGoodKeywords,
            $evaluation->matchedBadKeywords,
            'below_analysis_threshold',
        );
    }

    /**
     * selection 設定が有効かどうかを返す
     *
     * @return bool
     */
    public function enabled(): bool
    {
        return (bool) config('digestpipe.selection.enabled', true);
    }

    private function evaluateText(string $text): DigestItemSelectionResult
    {
        $score = $this->integerConfig('default_score');

        $matchedGoodKeywords = [];
        foreach ($this->keywords->positiveKeywords() as $keyword => $keywordScore) {
            if ($this->matches($text, $keyword)) {
                $matchedGoodKeywords[] = $keyword;
                $score += $keywordScore;
            }
        }

        $matchedBadKeywords = [];
        foreach ($this->keywords->negativeKeywords() as $keyword => $keywordScore) {
            if ($this->matches($text, $keyword)) {
                $matchedBadKeywords[] = $keyword;
                $score += $keywordScore;
            }
        }

        return new DigestItemSelectionResult($score, 'evaluated', $matchedGoodKeywords, $matchedBadKeywords, 'score_evaluated');
    }

    private function matches(string $text, string $keyword): bool
    {
        return preg_match('/' . preg_quote($keyword, '/') . '/iu', $text) === 1;
    }

    private function integerConfig(string $key): int
    {
        $value = config('digestpipe.selection.' . $key);

        if (! is_int($value)) {
            throw new UnexpectedValueException("digestpipe.selection.{$key} must be an integer.");
        }

        return $value;
    }
}
