<?php

namespace Tests\Feature;

use App\Filament\Resources\DigestItems\DigestItemResource;
use App\Filament\Resources\DigestItems\Pages\ListDigestItems;
use App\Filament\Resources\DigestItems\Pages\ViewDigestItem;
use App\Models\DigestItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * @internal
 */
class DigestItemReviewTest extends TestCase
{
    use RefreshDatabase;

    private int $digestItemSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-28T12:00:00Z'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function testDigestItemsTableHasManualRatingColumns(): void
    {
        self::assertTrue(Schema::hasColumn('digest_items', 'manual_rating'));
        self::assertTrue(Schema::hasColumn('digest_items', 'manual_rated_at'));
    }

    public function testManualRatingAcceptsAllowedValuesAndClearsTimestamp(): void
    {
        $item = $this->createDigestItem();

        self::assertNull($item->manual_rating);
        self::assertNull($item->manual_rated_at);

        foreach ([-1, 1, 2, 3, 4, 5] as $rating) {
            $item->setManualRating($rating);
            $item->save();
            $item->refresh();

            self::assertSame($rating, $item->manual_rating);
            self::assertSame(CarbonImmutable::now()->toJSON(), $item->manual_rated_at?->toJSON());
        }

        $item->clearManualRating();
        $item->save();
        $item->refresh();

        self::assertNull($item->manual_rating);
        self::assertNull($item->manual_rated_at);
    }

    public function testManualRatingRejectsInvalidValues(): void
    {
        $rejected = [];

        foreach ([0, -2, 6] as $rating) {
            try {
                DigestItem::validateManualRating($rating);
                self::fail('Invalid manual rating was accepted: ' . $rating);
            } catch (InvalidArgumentException) {
                $rejected[] = $rating;
            }
        }

        self::assertSame([0, -2, 6], $rejected);

        $this->expectException(InvalidArgumentException::class);

        $item = $this->createDigestItem();
        $item->setManualRatingAttribute('5');
    }

    public function testDigestItemReviewFilterScopes(): void
    {
        $ready = $this->createDigestItem([
            'source_key' => 'hacker_news',
            'selection_status' => 'selected',
            'article_content_status' => 'completed',
            'analysis_status' => 'completed',
            'manual_rating' => null,
        ]);
        $skipped = $this->createDigestItem([
            'source_key' => 'hacker_news',
            'selection_status' => 'skipped',
            'article_content_status' => 'completed',
            'analysis_status' => 'completed',
            'manual_rating' => -1,
        ]);
        $good = $this->createDigestItem([
            'source_key' => 'aws_news',
            'selection_status' => 'selected',
            'article_content_status' => 'completed',
            'analysis_status' => 'completed',
            'manual_rating' => 4,
        ]);
        $pending = $this->createDigestItem([
            'source_key' => 'aws_news',
            'selection_status' => 'selected',
            'article_content_status' => 'pending',
            'analysis_status' => 'pending',
            'manual_rating' => null,
        ]);

        self::assertSame([$ready->id, $good->id], $this->ids(DigestItemResource::scopeReadyForReview(DigestItem::query())));
        self::assertSame([$ready->id, $good->id, $pending->id], $this->ids(DigestItemResource::scopeSelected(DigestItem::query())));
        self::assertSame([$skipped->id], $this->ids(DigestItemResource::scopeSkipped(DigestItem::query())));
        self::assertSame([$ready->id, $pending->id], $this->ids(DigestItemResource::scopeUnrated(DigestItem::query())));
        self::assertSame([$good->id], $this->ids(DigestItemResource::scopeRatedGood(DigestItem::query())));
        self::assertSame([$skipped->id], $this->ids(DigestItemResource::scopeRatedBad(DigestItem::query())));
        self::assertSame([$ready->id, $skipped->id, $good->id], $this->ids(DigestItemResource::scopeContentFetched(DigestItem::query())));
        self::assertSame([$ready->id, $skipped->id, $good->id], $this->ids(DigestItemResource::scopeAnalysisCompleted(DigestItem::query())));
    }

    public function testDigestItemReviewListFiltersAndActionsWork(): void
    {
        $this->actingAsAdmin();
        $ready = $this->createDigestItem([
            'source_key' => 'hacker_news',
            'selection_status' => 'selected',
            'article_content_status' => 'completed',
            'analysis_status' => 'completed',
        ]);
        $notReady = $this->createDigestItem([
            'source_key' => 'aws_news',
            'selection_status' => 'selected',
            'article_content_status' => 'pending',
            'analysis_status' => 'pending',
        ]);
        $awsReady = $this->createDigestItem([
            'source_key' => 'aws_news',
            'selection_status' => 'selected',
            'article_content_status' => 'completed',
            'analysis_status' => 'completed',
        ]);

        /** @phpstan-ignore-next-line Filament table testing helpers are provided at runtime. */
        Livewire::test(ListDigestItems::class)
            ->assertTableFilterExists('ready_for_review')
            ->assertTableFilterExists('selected')
            ->assertTableFilterExists('skipped')
            ->assertTableFilterExists('unrated')
            ->assertTableFilterExists('rated_good')
            ->assertTableFilterExists('rated_bad')
            ->assertTableFilterExists('content_fetched')
            ->assertTableFilterExists('analysis_completed')
            ->assertTableFilterExists('source_key')
            ->assertCanSeeTableRecords([$ready])
            ->assertCanNotSeeTableRecords([$notReady])
            ->assertCanSeeTableRecords([$awsReady]);

        /** @phpstan-ignore-next-line Filament table testing helpers are provided at runtime. */
        Livewire::test(ListDigestItems::class)
            ->resetTableFilters()
            ->filterTable('source_key', 'aws_news')
            ->assertCanSeeTableRecords([$awsReady])
            ->assertCanNotSeeTableRecords([$ready, $notReady]);
    }

    public function testDigestItemReviewViewActionsSetAndClearRating(): void
    {
        $this->actingAsAdmin();
        $item = $this->createDigestItem([
            'selection_status' => 'selected',
            'article_content_status' => 'completed',
            'analysis_status' => 'completed',
            'analysis_json' => $this->analysisJson(),
            'article_content_text' => 'Reviewable article content.',
        ]);

        /** @phpstan-ignore-next-line Filament action testing helper is provided at runtime. */
        Livewire::test(ViewDigestItem::class, ['record' => $item->getKey()])
            ->callAction('rate_good_4')
            ->assertHasNoErrors();

        $item->refresh();

        self::assertSame(4, $item->manual_rating);
        self::assertSame(CarbonImmutable::now()->toJSON(), $item->manual_rated_at?->toJSON());
        self::assertSame(4, $item->manualGoodStars());
        self::assertFalse($item->isManuallyBad());

        /** @phpstan-ignore-next-line Filament action testing helper is provided at runtime. */
        Livewire::test(ViewDigestItem::class, ['record' => $item->getKey()])
            ->callAction('rate_good_4')
            ->assertHasNoErrors();

        $item->refresh();

        self::assertFalse($item->isManuallyRated());
        self::assertNull($item->manual_rating);
        self::assertNull($item->manual_rated_at);

        /** @phpstan-ignore-next-line Filament action testing helper is provided at runtime. */
        Livewire::test(ViewDigestItem::class, ['record' => $item->getKey()])
            ->callAction('rate_bad')
            ->assertHasNoErrors();

        $item->refresh();

        self::assertSame(-1, $item->manual_rating);
        self::assertTrue($item->isManuallyBad());
        self::assertNull($item->manualGoodStars());

        /** @phpstan-ignore-next-line Filament action testing helper is provided at runtime. */
        Livewire::test(ViewDigestItem::class, ['record' => $item->getKey()])
            ->callAction('rate_bad')
            ->assertHasNoErrors();

        $item->refresh();

        self::assertFalse($item->isManuallyRated());
        self::assertNull($item->manual_rating);
        self::assertNull($item->manual_rated_at);
    }

    public function testDigestItemReviewViewTranslatesSectionsTemporarily(): void
    {
        config([
            'digestpipe.translation.driver' => 'fake',
            'digestpipe.translation.max_chars' => 25,
        ]);
        $this->actingAsAdmin();
        $item = $this->createDigestItem([
            'title' => 'English title',
            'selection_status' => 'selected',
            'article_content_status' => 'completed',
            'analysis_status' => 'completed',
            'analysis_json' => $this->analysisJson(),
            'article_content_text' => 'Article content that is longer than configured max chars.',
        ]);

        /** @var Testable<ViewDigestItem> $component */
        $component = Livewire::test(ViewDigestItem::class, ['record' => $item->getKey()])
            ->call('translateArticle')
            ->call('translateArticleContent')
            ->call('translateAnalysis')
            ->assertHasNoErrors();

        /** @var array<string, string> $translations */
        $translations = $component->get('temporaryTranslations');
        /** @var array<string, bool> $truncation */
        $truncation = $component->get('translationTruncation');

        self::assertSame('[JA] English title', $translations['article.title']);
        self::assertSame('[JA] Article content that is l', $translations['article_content.text']);
        self::assertTrue($truncation['article_content.text']);
        self::assertSame('[JA] Short analysis brief.', $translations['analysis.brief']);
        self::assertSame('[JA] Detailed analysis summary', $translations['analysis.detailed_summary']);
        self::assertStringContainsString('[JA] Point 1', $translations['analysis.key_points']);
        Notification::assertNotified('Analysis translated.');

        $item->refresh();

        self::assertSame('English title', $item->title);
        self::assertSame('Article content that is longer than configured max chars.', $item->article_content_text);
        self::assertArrayNotHasKey('translated_title', $item->getAttributes());
    }

    public function testDigestItemReviewViewReportsTranslationMissingConfiguration(): void
    {
        config(['digestpipe.translation.driver' => 'none']);
        $this->actingAsAdmin();
        $item = $this->createDigestItem(['title' => 'English title']);

        Livewire::test(ViewDigestItem::class, ['record' => $item->getKey()])
            ->call('translateArticle')
            ->assertHasNoErrors();

        Notification::assertNotified('Translation is not configured.');
    }

    public function testAuthorizedAdminCanAccessDigestItemReviewPages(): void
    {
        $this->actingAsAdmin();
        $item = $this->createDigestItem([
            'selection_status' => 'selected',
            'article_content_status' => 'completed',
            'analysis_status' => 'completed',
            'article_content_text' => "Reviewable content line 1\nReviewable content line 2",
            'analysis_json' => array_merge($this->analysisJson(), [
                'brief' => "Brief line 1\nBrief line 2",
                'detailed_summary' => "Summary line 1\nSummary line 2",
            ]),
        ]);

        $this->get('/admin/digest-items')
            ->assertOk()
            ->assertSee('Digest Items');

        $this->get('/admin/digest-items/' . $item->id)
            ->assertOk()
            ->assertSee('Digest Item: ' . $item->title)
            ->assertSee($item->title)
            ->assertSeeHtml('Reviewable content line 1<br />')
            ->assertSeeHtml('Brief line 1<br />')
            ->assertSeeHtml('Summary line 1<br />')
            ->assertDontSee('Manual rating');
    }

    /**
     * @param Builder<DigestItem> $query
     *
     * @return list<int>
     */
    private function ids(Builder $query): array
    {
        /** @var list<int> $ids */
        $ids = array_values($query
            ->get()
            ->map(static fn (DigestItem $item): int => $item->id)
            ->sort()
            ->values()
            ->all());

        return $ids;
    }

    private function actingAsAdmin(): void
    {
        config(['digestpipe.admin.allowed_emails' => ['admin@example.test']]);
        $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createDigestItem(array $attributes = []): DigestItem
    {
        ++$this->digestItemSequence;
        $sequence = $this->digestItemSequence;

        return DigestItem::query()->create(array_merge([
            'source_key' => 'hacker_news',
            'source_name' => 'Hacker News',
            'external_id' => 'review-' . $sequence,
            'identity_hash' => hash('sha256', 'review-' . $sequence),
            'source_url' => 'https://example.test/articles/' . $sequence,
            'discussion_url' => 'https://example.test/discussions/' . $sequence,
            'title' => 'Review item ' . $sequence,
            'excerpt' => 'Review excerpt ' . $sequence,
            'published_at' => CarbonImmutable::now()->subMinutes(40),
            'fetched_at' => CarbonImmutable::now()->subMinutes(35),
            'content_hash' => hash('sha256', 'review-content-' . $sequence),
            'selection_status' => 'pending',
            'selection_score' => null,
            'selection_reason' => null,
            'selection_result' => [
                'matched_good_keywords' => ['Laravel'],
                'matched_bad_keywords' => [],
            ],
            'selection_evaluated_at' => CarbonImmutable::now()->subMinutes(30),
            'article_content_status' => 'pending',
            'article_content_text' => null,
            'article_content_error' => null,
            'article_content_fetched_at' => null,
            'analysis_status' => 'pending',
            'analysis_json' => null,
            'analysis_model' => null,
            'analysis_error' => null,
            'analyzed_at' => null,
            'manual_rating' => null,
            'manual_rated_at' => null,
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function analysisJson(): array
    {
        return [
            'brief' => 'Short analysis brief.',
            'detailed_summary' => 'Detailed analysis summary.',
            'key_points' => ['Point 1', 'Point 2'],
            'content' => [
                'limitations' => 'No major limitation.',
            ],
            'classification' => [
                'content_type' => 'technical_article',
                'importance' => 4,
                'confidence' => 0.9,
            ],
        ];
    }
}
