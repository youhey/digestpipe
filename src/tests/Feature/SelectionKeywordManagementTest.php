<?php

namespace Tests\Feature;

use App\Items\SelectionKeywordRepository;
use App\Models\SelectionKeyword;
use Database\Seeders\SelectionKeywordSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * @internal
 */
class SelectionKeywordManagementTest extends TestCase
{
    use RefreshDatabase;

    public function testSelectionKeywordsTableHasExpectedColumns(): void
    {
        self::assertTrue(Schema::hasTable('selection_keywords'));

        foreach (['id', 'keyword', 'type', 'score', 'enabled', 'locale', 'category', 'notes', 'sort_order', 'match_mode', 'created_at', 'updated_at'] as $column) {
            self::assertTrue(Schema::hasColumn('selection_keywords', $column), "Missing column: {$column}");
        }
    }

    public function testSeederInsertsDefaultPositiveAndNegativeKeywords(): void
    {
        $this->seed(SelectionKeywordSeeder::class);

        $this->assertDatabaseHas('selection_keywords', [
            'keyword' => 'Laravel',
            'type' => 'positive',
            'score' => 15,
            'enabled' => true,
            'category' => 'laravel',
            'match_mode' => 'word_boundary',
        ]);
        $this->assertDatabaseHas('selection_keywords', [
            'keyword' => 'crypto',
            'type' => 'negative',
            'score' => -100,
            'enabled' => true,
            'category' => 'crypto',
            'match_mode' => 'word_boundary',
        ]);
    }

    public function testSeederUsesTunedDefaultKeywordSet(): void
    {
        $this->seed(SelectionKeywordSeeder::class);

        foreach (['token', 'tokens', 'トークン'] as $keyword) {
            $this->assertDatabaseMissing('selection_keywords', [
                'keyword' => $keyword,
                'type' => 'negative',
            ]);
        }

        $this->assertDatabaseMissing('selection_keywords', [
            'keyword' => 'AWS',
            'type' => 'positive',
        ]);

        foreach ([
            ['crypto token', 'negative', -60, 'crypto', 'exact_phrase'],
            ['governance token', 'negative', -60, 'crypto', 'exact_phrase'],
            ['NFT token', 'negative', -80, 'crypto', 'exact_phrase'],
            ['Amazon Linux', 'positive', 8, 'aws', 'exact_phrase'],
            ['CloudWatch', 'positive', 8, 'aws', 'word_boundary'],
            ['VPC', 'positive', 8, 'aws', 'word_boundary'],
            ['ALB', 'positive', 8, 'aws', 'word_boundary'],
            ['AWS EBS', 'positive', 8, 'aws', 'exact_phrase'],
            ['AWS EFS', 'positive', 8, 'aws', 'exact_phrase'],
            ['AWS SQS', 'positive', 8, 'aws', 'exact_phrase'],
            ['AWS SNS', 'positive', 8, 'aws', 'exact_phrase'],
            ['Step Functions', 'positive', 8, 'aws', 'exact_phrase'],
            ['GitHub Actions', 'positive', 12, 'devops', 'exact_phrase'],
            ['CVE', 'positive', 10, 'security', 'word_boundary'],
            ['Golang', 'positive', 8, 'programming', 'word_boundary'],
            ['FFmpeg', 'positive', 6, 'tooling', 'word_boundary'],
            ['AGENTS.md', 'positive', 10, 'agent', 'exact_phrase'],
            ['Cloudflare', 'positive', 8, 'cloud', 'word_boundary'],
            ['Laravel Cloud', 'positive', 12, 'laravel', 'exact_phrase'],
            ['さくらインターネット', 'positive', 8, 'cloud', 'contains'],
        ] as [$keyword, $type, $score, $category, $matchMode]) {
            $this->assertDatabaseHas('selection_keywords', [
                'keyword' => $keyword,
                'type' => $type,
                'score' => $score,
                'category' => $category,
                'match_mode' => $matchMode,
            ]);
        }
    }

    public function testSeederDoesNotOverwriteExistingEditedKeyword(): void
    {
        SelectionKeyword::query()->create([
            'keyword' => 'Laravel',
            'type' => 'positive',
            'score' => 99,
            'enabled' => false,
            'locale' => 'ja',
            'category' => 'php',
            'sort_order' => 999,
            'match_mode' => 'contains',
        ]);

        $this->seed(SelectionKeywordSeeder::class);

        $this->assertDatabaseHas('selection_keywords', [
            'keyword' => 'Laravel',
            'type' => 'positive',
            'score' => 99,
            'enabled' => false,
            'locale' => 'ja',
            'sort_order' => 999,
            'match_mode' => 'contains',
        ]);
    }

    public function testRepositoryReturnsEnabledPositiveAndNegativeKeywordMaps(): void
    {
        $this->createKeyword('AWS', 'positive', 12, sortOrder: 20);
        $this->createKeyword('Laravel', 'positive', 15, sortOrder: 10);
        $this->createKeyword('crypto', 'negative', -100, sortOrder: 30);
        $this->createKeyword('disabled', 'negative', -10, enabled: false, sortOrder: 40);

        $repository = app(SelectionKeywordRepository::class);

        self::assertSame([
            'Laravel' => 15,
            'AWS' => 12,
        ], $repository->positiveKeywords());
        self::assertSame([
            'crypto' => -100,
        ], $repository->negativeKeywords());
    }

    public function testOrderingFallsBackToIdWhenSortOrderMatches(): void
    {
        $first = $this->createKeyword('first keyword', 'positive', 1, sortOrder: 10);
        $second = $this->createKeyword('second keyword', 'positive', 2, sortOrder: 10);

        self::assertSame([
            $first->keyword => $first->score,
            $second->keyword => $second->score,
        ], app(SelectionKeywordRepository::class)->positiveKeywords());
    }

    public function testValidationRejectsEmptyKeywordAfterTrim(): void
    {
        $this->expectException(ValidationException::class);

        $this->createKeyword('   ', 'positive', 1);
    }

    public function testValidationRejectsUnsupportedType(): void
    {
        $this->expectException(ValidationException::class);

        $this->createKeyword('Laravel', 'neutral', 1);
    }

    public function testValidationRejectsZeroScore(): void
    {
        $this->expectException(ValidationException::class);

        $this->createKeyword('Laravel', 'positive', 0);
    }

    public function testValidationRejectsPositiveKeywordWithNegativeScore(): void
    {
        $this->expectException(ValidationException::class);

        $this->createKeyword('Laravel', 'positive', -1);
    }

    public function testValidationRejectsNegativeKeywordWithPositiveScore(): void
    {
        $this->expectException(ValidationException::class);

        $this->createKeyword('crypto', 'negative', 1);
    }

    public function testValidationRejectsUnsupportedLocale(): void
    {
        $this->expectException(ValidationException::class);

        $this->createKeyword('Laravel', 'positive', 1, locale: 'fr');
    }

    public function testValidationRejectsUnsupportedMatchMode(): void
    {
        $this->expectException(ValidationException::class);

        $this->createKeyword('Laravel', 'positive', 1, matchMode: 'regex');
    }

    private function createKeyword(
        string $keyword,
        string $type,
        int $score,
        bool $enabled = true,
        string $locale = 'any',
        ?string $category = null,
        int $sortOrder = 100,
        string $matchMode = 'contains',
    ): SelectionKeyword {
        return SelectionKeyword::query()->create([
            'keyword' => $keyword,
            'type' => $type,
            'score' => $score,
            'enabled' => $enabled,
            'locale' => $locale,
            'category' => $category,
            'sort_order' => $sortOrder,
            'match_mode' => $matchMode,
        ]);
    }
}
