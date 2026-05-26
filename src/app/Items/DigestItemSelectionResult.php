<?php

namespace App\Items;

/**
 * Digest Itemの selection 評価結果
 */
class DigestItemSelectionResult
{
    /** @var int selection 対象テキストから算出したスコア */
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
