<?php

namespace Tests\Feature;

use App\Filament\Resources\FeedSources\Pages\CreateFeedSource;
use App\Filament\Resources\FeedSources\Pages\EditFeedSource;
use App\Filament\Resources\PositiveKeywords\Pages\CreatePositiveKeyword;
use App\Filament\Resources\PositiveKeywords\Pages\EditPositiveKeyword;
use App\Models\FeedSource;
use App\Models\SelectionKeyword;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * @internal
 */
class FilamentResourceNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['digestpipe.admin.allowed_emails' => ['admin@example.test']]);

        $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
    }

    public function testFeedSourceCreatePageSendsSuccessNotification(): void
    {
        Livewire::test(CreateFeedSource::class)
            ->set('data.key', 'example_feed')
            ->set('data.name', 'Example Feed')
            ->set('data.url', 'https://example.com/feed.xml')
            ->set('data.language', 'en')
            ->set('data.enabled', true)
            ->set('data.analysis_enabled', true)
            ->set('data.tier', 'core')
            ->set('data.category', 'programming')
            ->call('create')
            ->assertHasNoErrors();

        Notification::assertNotified('Feed Source を作成しました。');
    }

    public function testFeedSourceCreatePageSendsFailureNotificationForValidationErrors(): void
    {
        Livewire::test(CreateFeedSource::class)
            ->call('create')
            ->assertHasErrors();

        Notification::assertNotified('Feed Source を作成できませんでした。');
    }

    public function testFeedSourceEditPageSendsSuccessNotification(): void
    {
        $feedSource = $this->feedSource();

        Livewire::test(EditFeedSource::class, ['record' => $feedSource->getKey()])
            ->set('data.name', 'Updated Feed')
            ->call('save')
            ->assertHasNoErrors();

        Notification::assertNotified('Feed Source を更新しました。');
    }

    public function testFeedSourceEditPageDeleteActionSendsSuccessNotification(): void
    {
        $feedSource = $this->feedSource();

        /** @phpstan-ignore-next-line Filament action testing helper is provided at runtime. */
        Livewire::test(EditFeedSource::class, ['record' => $feedSource->getKey()])
            ->callAction(DeleteAction::class);

        Notification::assertNotified('Feed Source を削除しました。');

        $this->assertDatabaseMissing('feed_sources', ['id' => $feedSource->getKey()]);
    }

    public function testSelectionKeywordCreatePageSendsSuccessNotification(): void
    {
        Livewire::test(CreatePositiveKeyword::class)
            ->set('data.keyword', 'Example Keyword')
            ->set('data.match_mode', 'exact_phrase')
            ->set('data.score', 5)
            ->set('data.enabled', true)
            ->set('data.locale', 'en')
            ->set('data.category', 'testing')
            ->set('data.notes', null)
            ->call('create')
            ->assertHasNoErrors();

        Notification::assertNotified('Positive Keyword を作成しました。');
    }

    public function testSelectionKeywordCreatePageSendsFailureNotificationForValidationErrors(): void
    {
        Livewire::test(CreatePositiveKeyword::class)
            ->call('create')
            ->assertHasErrors();

        Notification::assertNotified('Positive Keyword を作成できませんでした。');
    }

    public function testSelectionKeywordEditPageSendsSuccessNotification(): void
    {
        $keyword = $this->selectionKeyword();

        Livewire::test(EditPositiveKeyword::class, ['record' => $keyword->getKey()])
            ->set('data.score', 7)
            ->call('save')
            ->assertHasNoErrors();

        Notification::assertNotified('Positive Keyword を更新しました。');
    }

    public function testSelectionKeywordEditPageDeleteActionSendsSuccessNotification(): void
    {
        $keyword = $this->selectionKeyword();

        /** @phpstan-ignore-next-line Filament action testing helper is provided at runtime. */
        Livewire::test(EditPositiveKeyword::class, ['record' => $keyword->getKey()])
            ->callAction(DeleteAction::class);

        Notification::assertNotified('Positive Keyword を削除しました。');

        $this->assertDatabaseMissing('selection_keywords', ['id' => $keyword->getKey()]);
    }

    private function feedSource(): FeedSource
    {
        return FeedSource::query()->create([
            'key' => 'example_feed',
            'name' => 'Example Feed',
            'url' => 'https://example.com/feed.xml',
            'language' => 'en',
            'enabled' => true,
            'analysis_enabled' => true,
            'tier' => 'core',
            'category' => 'programming',
            'sort_order' => 10,
        ]);
    }

    private function selectionKeyword(): SelectionKeyword
    {
        return SelectionKeyword::query()->create([
            'keyword' => 'Example Keyword',
            'type' => 'positive',
            'score' => 5,
            'enabled' => true,
            'locale' => 'en',
            'category' => 'testing',
            'sort_order' => 10,
            'match_mode' => 'exact_phrase',
        ]);
    }
}
