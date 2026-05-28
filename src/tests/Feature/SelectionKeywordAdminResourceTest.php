<?php

namespace Tests\Feature;

use App\Filament\Resources\NegativeKeywords\NegativeKeywordResource;
use App\Filament\Resources\NegativeKeywords\Pages\CreateNegativeKeyword;
use App\Filament\Resources\NegativeKeywords\Pages\EditNegativeKeyword;
use App\Filament\Resources\NegativeKeywords\Pages\ListNegativeKeywords;
use App\Filament\Resources\PositiveKeywords\Pages\CreatePositiveKeyword;
use App\Filament\Resources\PositiveKeywords\Pages\EditPositiveKeyword;
use App\Filament\Resources\PositiveKeywords\Pages\ListPositiveKeywords;
use App\Filament\Resources\PositiveKeywords\PositiveKeywordResource;
use App\Models\SelectionKeyword;
use App\Models\User;
use Filament\Forms\Components\Field;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * @internal
 */
class SelectionKeywordAdminResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['digestpipe.admin.allowed_emails' => ['admin@example.test']]);

        $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
    }

    public function testPositiveKeywordsIndexOnlyShowsPositiveRecords(): void
    {
        $this->keyword('Laravel', 'positive', 10);
        $this->keyword('crypto token', 'negative', -60);

        $component = Livewire::test(ListPositiveKeywords::class);

        $component->assertSee('Laravel');
        $component->assertDontSee('crypto token');
    }

    public function testNegativeKeywordsIndexOnlyShowsNegativeRecords(): void
    {
        $this->keyword('Laravel', 'positive', 10);
        $this->keyword('crypto token', 'negative', -60);

        $component = Livewire::test(ListNegativeKeywords::class);

        $component->assertSee('crypto token');
        $component->assertDontSee('Laravel');
    }

    public function testPositiveCreateSetsPositiveType(): void
    {
        Livewire::test(CreatePositiveKeyword::class)
            ->set('data.keyword', 'Positive Created')
            ->set('data.match_mode', 'exact_phrase')
            ->set('data.score', 12)
            ->set('data.enabled', true)
            ->set('data.locale', 'en')
            ->set('data.category', 'testing')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('selection_keywords', [
            'keyword' => 'Positive Created',
            'type' => 'positive',
            'score' => 12,
        ]);
    }

    public function testNegativeCreateSetsNegativeType(): void
    {
        Livewire::test(CreateNegativeKeyword::class)
            ->set('data.keyword', 'Negative Created')
            ->set('data.match_mode', 'exact_phrase')
            ->set('data.score', -12)
            ->set('data.enabled', true)
            ->set('data.locale', 'en')
            ->set('data.category', 'testing')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('selection_keywords', [
            'keyword' => 'Negative Created',
            'type' => 'negative',
            'score' => -12,
        ]);
    }

    public function testPositiveAndNegativeFormsDoNotExposeTypeField(): void
    {
        $positiveFields = array_map(
            static fn (Field $component): string => $component->getName(),
            PositiveKeywordResource::formComponents(),
        );
        $negativeFields = array_map(
            static fn (Field $component): string => $component->getName(),
            NegativeKeywordResource::formComponents(),
        );

        self::assertNotContains('type', $positiveFields);
        self::assertNotContains('type', $negativeFields);
    }

    public function testPositiveScoreAcceptsConfiguredRange(): void
    {
        Livewire::test(CreatePositiveKeyword::class)
            ->set('data.keyword', 'Positive Range')
            ->set('data.match_mode', 'exact_phrase')
            ->set('data.score', 100)
            ->set('data.enabled', true)
            ->set('data.locale', 'en')
            ->call('create')
            ->assertHasNoErrors();
    }

    public function testPositiveScoreRejectsZeroAndNegativeValues(): void
    {
        Livewire::test(CreatePositiveKeyword::class)
            ->set('data.keyword', 'Positive Zero')
            ->set('data.match_mode', 'exact_phrase')
            ->set('data.score', 0)
            ->set('data.enabled', true)
            ->set('data.locale', 'en')
            ->call('create')
            ->assertHasErrors(['data.score']);

        Livewire::test(CreatePositiveKeyword::class)
            ->set('data.keyword', 'Positive Negative')
            ->set('data.match_mode', 'exact_phrase')
            ->set('data.score', -1)
            ->set('data.enabled', true)
            ->set('data.locale', 'en')
            ->call('create')
            ->assertHasErrors(['data.score']);
    }

    public function testNegativeScoreAcceptsConfiguredRange(): void
    {
        Livewire::test(CreateNegativeKeyword::class)
            ->set('data.keyword', 'Negative Range')
            ->set('data.match_mode', 'exact_phrase')
            ->set('data.score', -100)
            ->set('data.enabled', true)
            ->set('data.locale', 'en')
            ->call('create')
            ->assertHasNoErrors();
    }

    public function testNegativeScoreRejectsZeroAndPositiveValues(): void
    {
        Livewire::test(CreateNegativeKeyword::class)
            ->set('data.keyword', 'Negative Zero')
            ->set('data.match_mode', 'exact_phrase')
            ->set('data.score', 0)
            ->set('data.enabled', true)
            ->set('data.locale', 'en')
            ->call('create')
            ->assertHasErrors(['data.score']);

        Livewire::test(CreateNegativeKeyword::class)
            ->set('data.keyword', 'Negative Positive')
            ->set('data.match_mode', 'exact_phrase')
            ->set('data.score', 1)
            ->set('data.enabled', true)
            ->set('data.locale', 'en')
            ->call('create')
            ->assertHasErrors(['data.score']);
    }

    public function testEditPagesKeepExistingType(): void
    {
        $positive = $this->keyword('Positive Existing', 'positive', 10);
        $negative = $this->keyword('Negative Existing', 'negative', -10);

        Livewire::test(EditPositiveKeyword::class, ['record' => $positive->getKey()])
            ->set('data.score', 20)
            ->call('save')
            ->assertHasNoErrors();
        Livewire::test(EditNegativeKeyword::class, ['record' => $negative->getKey()])
            ->set('data.score', -20)
            ->call('save')
            ->assertHasNoErrors();

        self::assertSame('positive', $positive->refresh()->type);
        self::assertSame('negative', $negative->refresh()->type);
    }

    private function keyword(string $keyword, string $type, int $score): SelectionKeyword
    {
        return SelectionKeyword::query()->create([
            'keyword' => $keyword,
            'type' => $type,
            'score' => $score,
            'enabled' => true,
            'locale' => 'en',
            'category' => 'testing',
            'sort_order' => 10,
            'match_mode' => 'exact_phrase',
        ]);
    }
}
