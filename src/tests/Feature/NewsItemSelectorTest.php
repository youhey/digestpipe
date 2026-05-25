<?php

namespace Tests\Feature;

use App\Items\NewsItemSelector;
use App\Models\NewsItem;
use Tests\TestCase;

/**
 * @internal
 */
class NewsItemSelectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'digestpipe.selection' => [
                'enabled' => true,
                'default_score' => 0,
                'analysis_threshold' => 10,
                'skip_threshold' => -50,
                'positive_keywords' => [
                    'Laravel' => 15,
                    'AWS' => 12,
                    'PHP' => 8,
                    '自宅サーバー' => 12,
                ],
                'negative_keywords' => [
                    'crypto' => -100,
                    'blockchain' => -100,
                    'token' => -10,
                    '資金調達' => -40,
                ],
            ],
        ]);
    }

    public function testPositiveKeywordScoringSelectsItem(): void
    {
        $result = $this->selector()->evaluate($this->newsItem('Laravel deployment guide', null));

        self::assertSame(15, $result->score);
        self::assertSame('selected', $result->status);
        self::assertSame(['Laravel'], $result->matchedGoodKeywords);
        self::assertSame([], $result->matchedBadKeywords);
        self::assertSame('above_analysis_threshold', $result->reason);
    }

    public function testNegativeKeywordScoringSkipsItem(): void
    {
        $result = $this->selector()->evaluate($this->newsItem('Crypto and blockchain update', null));

        self::assertSame(-200, $result->score);
        self::assertSame('skipped', $result->status);
        self::assertSame([], $result->matchedGoodKeywords);
        self::assertSame(['crypto', 'blockchain'], $result->matchedBadKeywords);
        self::assertSame('below_skip_threshold', $result->reason);
    }

    public function testMixedPositiveAndNegativeScoringUsesTotalScore(): void
    {
        $result = $this->selector()->evaluate($this->newsItem('Laravel token handling', 'AWS article'));

        self::assertSame(17, $result->score);
        self::assertSame('selected', $result->status);
        self::assertSame(['Laravel', 'AWS'], $result->matchedGoodKeywords);
        self::assertSame(['token'], $result->matchedBadKeywords);
    }

    public function testItemBelowAnalysisThresholdIsSkipped(): void
    {
        $result = $this->selector()->evaluate($this->newsItem('Plain release note', null));

        self::assertSame(0, $result->score);
        self::assertSame('skipped', $result->status);
        self::assertSame('below_analysis_threshold', $result->reason);
    }

    public function testJapaneseKeywordMatchingSelectsItem(): void
    {
        $result = $this->selector()->evaluate($this->newsItem('自宅サーバーの運用メモ', null));

        self::assertSame(12, $result->score);
        self::assertSame('selected', $result->status);
        self::assertSame(['自宅サーバー'], $result->matchedGoodKeywords);
    }

    private function selector(): NewsItemSelector
    {
        return new NewsItemSelector();
    }

    private function newsItem(string $title, ?string $excerpt): NewsItem
    {
        return new NewsItem([
            'title' => $title,
            'excerpt' => $excerpt,
        ]);
    }
}
