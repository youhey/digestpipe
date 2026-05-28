<?php

namespace Tests\Feature;

use App\Items\DigestItemSelector;
use App\Models\DigestItem;
use App\Models\SelectionKeyword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @internal
 */
class DigestItemSelectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'digestpipe.selection' => [
                'enabled' => true,
                'default_score' => 0,
                'analysis_threshold' => 10,
                'skip_threshold' => -50,
            ],
        ]);

        $this->createSelectionKeyword('Laravel', 'positive', 15, 10);
        $this->createSelectionKeyword('AWS', 'positive', 12, 20);
        $this->createSelectionKeyword('PHP', 'positive', 8, 30);
        $this->createSelectionKeyword('自宅サーバー', 'positive', 12, 40);
        $this->createSelectionKeyword('crypto', 'negative', -100, 50);
        $this->createSelectionKeyword('blockchain', 'negative', -100, 60);
        $this->createSelectionKeyword('token', 'negative', -10, 70);
        $this->createSelectionKeyword('資金調達', 'negative', -40, 80);
    }

    public function testPreContentPositiveKeywordDefersFinalSelection(): void
    {
        $result = $this->selector()->evaluatePreContent($this->digestItem('Laravel deployment guide', null));

        self::assertSame(15, $result->score);
        self::assertSame('needs_content', $result->status);
        self::assertSame(['Laravel'], $result->matchedGoodKeywords);
        self::assertSame([], $result->matchedBadKeywords);
        self::assertSame('pre_content_selection_deferred', $result->reason);
    }

    public function testPreContentLowScoreDefersFinalSelection(): void
    {
        $result = $this->selector()->evaluatePreContent($this->digestItem('Plain release note', null));

        self::assertSame(0, $result->score);
        self::assertSame('needs_content', $result->status);
        self::assertSame('pre_content_selection_deferred', $result->reason);
    }

    public function testPreContentHardNegativeSkipsItem(): void
    {
        $result = $this->selector()->evaluatePreContent($this->digestItem('Crypto and blockchain update', null));

        self::assertSame(-200, $result->score);
        self::assertSame('skipped', $result->status);
        self::assertSame([], $result->matchedGoodKeywords);
        self::assertSame(['crypto', 'blockchain'], $result->matchedBadKeywords);
        self::assertSame('below_skip_threshold', $result->reason);
    }

    public function testMixedPositiveAndNegativeScoringUsesTotalScore(): void
    {
        $result = $this->selector()->evaluate($this->digestItem('Laravel token handling', 'AWS article'));

        self::assertSame(17, $result->score);
        self::assertSame('selected', $result->status);
        self::assertSame(['Laravel', 'AWS'], $result->matchedGoodKeywords);
        self::assertSame(['token'], $result->matchedBadKeywords);
    }

    public function testPostContentItemBelowAnalysisThresholdIsSkipped(): void
    {
        $result = $this->selector()->evaluatePostContent($this->digestItem('Plain release note', null));

        self::assertSame(0, $result->score);
        self::assertSame('skipped', $result->status);
        self::assertSame('below_analysis_threshold', $result->reason);
    }

    public function testPostContentArticleTextCanSelectItem(): void
    {
        $item = $this->digestItem('Plain title', 'Plain excerpt');
        $item->article_content_text = 'This article explains Laravel deployment.';

        $result = $this->selector()->evaluatePostContent($item);

        self::assertSame(15, $result->score);
        self::assertSame('selected', $result->status);
        self::assertSame(['Laravel'], $result->matchedGoodKeywords);
        self::assertSame('above_analysis_threshold', $result->reason);
    }

    public function testJapaneseKeywordMatchingSelectsItem(): void
    {
        $result = $this->selector()->evaluatePostContent($this->digestItem('自宅サーバーの運用メモ', null));

        self::assertSame(12, $result->score);
        self::assertSame('selected', $result->status);
        self::assertSame(['自宅サーバー'], $result->matchedGoodKeywords);
    }

    public function testContainsMatchModeMatchesSubstrings(): void
    {
        $this->createSelectionKeyword('art', 'positive', 5, 90, 'contains');

        $result = $this->selector()->evaluatePostContent($this->digestItem('Article extraction notes', null));

        self::assertSame(5, $result->score);
        self::assertSame(['art'], $result->matchedGoodKeywords);
    }

    public function testContainsMatchModeMatchesJapaneseText(): void
    {
        $this->createSelectionKeyword('コンテナ', 'positive', 8, 90, 'contains');

        $result = $this->selector()->evaluatePostContent($this->digestItem('軽量コンテナ運用', null));

        self::assertSame(8, $result->score);
        self::assertSame(['コンテナ'], $result->matchedGoodKeywords);
    }

    public function testWordBoundaryMatchModeMatchesStandaloneCli(): void
    {
        $this->createSelectionKeyword('CLI', 'positive', 8, 90, 'word_boundary');

        $result = $this->selector()->evaluatePostContent($this->digestItem('CLI tooling guide', null));

        self::assertSame(8, $result->score);
        self::assertSame(['CLI'], $result->matchedGoodKeywords);
    }

    public function testWordBoundaryMatchModeDoesNotMatchCliInsideClient(): void
    {
        $this->createSelectionKeyword('CLI', 'positive', 8, 90, 'word_boundary');

        $result = $this->selector()->evaluatePostContent($this->digestItem('client tooling guide', null));

        self::assertSame(0, $result->score);
        self::assertSame([], $result->matchedGoodKeywords);
    }

    public function testWordBoundaryMatchModeMatchesStandaloneDefi(): void
    {
        $this->createSelectionKeyword('DeFi', 'negative', -80, 90, 'word_boundary');

        $result = $this->selector()->evaluatePostContent($this->digestItem('DeFi market update', null));

        self::assertSame(-80, $result->score);
        self::assertSame(['DeFi'], $result->matchedBadKeywords);
    }

    public function testWordBoundaryMatchModeDoesNotMatchDefiInsideDefined(): void
    {
        $this->createSelectionKeyword('DeFi', 'negative', -80, 90, 'word_boundary');

        $result = $this->selector()->evaluatePostContent($this->digestItem('well-defined interface', null));

        self::assertSame(0, $result->score);
        self::assertSame([], $result->matchedBadKeywords);
    }

    public function testWordBoundaryMatchModeMatchesS3Token(): void
    {
        $this->createSelectionKeyword('S3', 'positive', 8, 90, 'word_boundary');

        $result = $this->selector()->evaluatePostContent($this->digestItem('S3 bucket lifecycle rules', null));

        self::assertSame(8, $result->score);
        self::assertSame(['S3'], $result->matchedGoodKeywords);
    }

    public function testExactPhraseMatchModeMatchesPhrasesAndSymbols(): void
    {
        SelectionKeyword::query()->delete();

        $this->createSelectionKeyword('GitHub Actions', 'positive', 10, 90, 'exact_phrase');
        $this->createSelectionKeyword('PHP-CS-Fixer', 'positive', 8, 100, 'exact_phrase');
        $this->createSelectionKeyword('AGENTS.md', 'positive', 6, 110, 'exact_phrase');

        $result = $this->selector()->evaluatePostContent($this->digestItem('github actions and php-cs-fixer rules in AGENTS.md', null));

        self::assertSame(24, $result->score);
        self::assertSame(['GitHub Actions', 'PHP-CS-Fixer', 'AGENTS.md'], $result->matchedGoodKeywords);
    }

    public function testKeywordsAreEscapedAndNotTreatedAsRegex(): void
    {
        $this->createSelectionKeyword('C++', 'positive', 7, 90, 'exact_phrase');

        $result = $this->selector()->evaluatePostContent($this->digestItem('C++ build notes', null));

        self::assertSame(7, $result->score);
        self::assertSame(['C++'], $result->matchedGoodKeywords);
    }

    private function selector(): DigestItemSelector
    {
        return app(DigestItemSelector::class);
    }

    private function digestItem(string $title, ?string $excerpt): DigestItem
    {
        return new DigestItem([
            'title' => $title,
            'excerpt' => $excerpt,
        ]);
    }

    private function createSelectionKeyword(string $keyword, string $type, int $score, int $sortOrder, string $matchMode = 'contains'): void
    {
        SelectionKeyword::query()->create([
            'keyword' => $keyword,
            'type' => $type,
            'score' => $score,
            'enabled' => true,
            'locale' => 'any',
            'category' => null,
            'sort_order' => $sortOrder,
            'match_mode' => $matchMode,
        ]);
    }
}
