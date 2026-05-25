<?php

namespace App\Items;

use App\Models\NewsItem;
use UnexpectedValueException;

/**
 * RSS レベルの title / excerpt だけでニュース記事アイテムを選別する
 */
class NewsItemSelector
{
    /**
     * ニュース記事アイテムの title / excerpt を評価して選別結果を返す
     *
     * @param NewsItem $item
     *
     * @return NewsItemSelectionResult
     */
    public function evaluate(NewsItem $item): NewsItemSelectionResult
    {
        $text = $item->title . "\n" . ($item->excerpt ?? '');
        $score = $this->integerConfig('default_score');

        $matchedGoodKeywords = [];
        foreach ($this->keywordScores('positive_keywords') as $keyword => $keywordScore) {
            if ($this->matches($text, $keyword)) {
                $matchedGoodKeywords[] = $keyword;
                $score += $keywordScore;
            }
        }

        $matchedBadKeywords = [];
        foreach ($this->keywordScores('negative_keywords') as $keyword => $keywordScore) {
            if ($this->matches($text, $keyword)) {
                $matchedBadKeywords[] = $keyword;
                $score += $keywordScore;
            }
        }

        if ($score <= $this->integerConfig('skip_threshold')) {
            return new NewsItemSelectionResult($score, 'skipped', $matchedGoodKeywords, $matchedBadKeywords, 'below_skip_threshold');
        }

        if ($score >= $this->integerConfig('analysis_threshold')) {
            return new NewsItemSelectionResult($score, 'selected', $matchedGoodKeywords, $matchedBadKeywords, 'above_analysis_threshold');
        }

        return new NewsItemSelectionResult($score, 'skipped', $matchedGoodKeywords, $matchedBadKeywords, 'below_analysis_threshold');
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

    /**
     * @return array<string, int>
     */
    private function keywordScores(string $key): array
    {
        $configuredScores = config('digestpipe.selection.' . $key, []);

        if (! is_array($configuredScores)) {
            throw new UnexpectedValueException("digestpipe.selection.{$key} must be an array.");
        }

        $scores = [];
        foreach ($configuredScores as $keyword => $score) {
            if (! is_string($keyword) || ! is_int($score)) {
                throw new UnexpectedValueException("digestpipe.selection.{$key} must be an array of keyword score pairs.");
            }

            $scores[$keyword] = $score;
        }

        return $scores;
    }
}
