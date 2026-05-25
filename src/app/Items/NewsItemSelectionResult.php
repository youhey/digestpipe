<?php

namespace App\Items;

/**
 * ニュース記事アイテムの RSS レベル選別結果
 */
class NewsItemSelectionResult
{
    /** @var int RSS レベル情報から算出した選別スコア */
    public readonly int $score;

    /** @var string 選別後の Status */
    public readonly string $status;

    /** @var list<string> 加点に使われた keyword */
    public readonly array $matchedGoodKeywords;

    /** @var list<string> 減点に使われた keyword */
    public readonly array $matchedBadKeywords;

    /** @var string 選別理由を表す短い文字列 */
    public readonly string $reason;

    /**
     * Constructor
     *
     * @param int $score
     * @param string $status
     * @param list<string> $matchedGoodKeywords
     * @param list<string> $matchedBadKeywords
     * @param string $reason
     */
    public function __construct(int $score, string $status, array $matchedGoodKeywords, array $matchedBadKeywords, string $reason)
    {
        $this->score = $score;
        $this->status = $status;
        $this->matchedGoodKeywords = $matchedGoodKeywords;
        $this->matchedBadKeywords = $matchedBadKeywords;
        $this->reason = $reason;
    }

    /**
     * DB に保存する selection_result JSON を返す
     *
     * @return array{score: int, status: string, matched_good_keywords: list<string>, matched_bad_keywords: list<string>, reason: string}
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'status' => $this->status,
            'matched_good_keywords' => $this->matchedGoodKeywords,
            'matched_bad_keywords' => $this->matchedBadKeywords,
            'reason' => $this->reason,
        ];
    }
}
