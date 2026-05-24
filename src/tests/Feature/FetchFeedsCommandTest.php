<?php

namespace Tests\Feature;

use App\Models\NewsItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\PendingCommand;
use RuntimeException;
use Tests\TestCase;

/**
 * @internal
 */
class FetchFeedsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function testCommandCanRun(): void
    {
        $this->configureFeedSources();

        Http::fake([
            'https://feeds.example.test/hacker-news.xml' => Http::response(self::rssFeed(), 200),
        ]);

        $this->fetchFeeds()
            ->assertSuccessful();

        $this->assertDatabaseCount('news_items', 2);
    }

    public function testSourceOptionFetchesOnlyTheSelectedSource(): void
    {
        $this->configureFeedSources([
            [
                'key' => 'hacker_news',
                'name' => 'Hacker News',
                'url' => 'https://feeds.example.test/hacker-news.xml',
                'language' => 'en',
                'enabled' => true,
            ],
            [
                'key' => 'reuters_top',
                'name' => 'Reuters Top News',
                'url' => 'https://feeds.example.test/reuters.rdf',
                'language' => 'en',
                'enabled' => true,
            ],
        ]);

        Http::fake([
            'https://feeds.example.test/hacker-news.xml' => Http::response(self::rssFeed(), 200),
            'https://feeds.example.test/reuters.rdf' => Http::response(self::rdfFeed(), 200),
        ]);

        $this->fetchFeeds(['--source' => 'reuters_top'])
            ->assertSuccessful();

        $this->assertDatabaseCount('news_items', 1);
        $this->assertDatabaseHas('news_items', [
            'source_key' => 'reuters_top',
            'title' => 'Reuters item',
        ]);

        Http::assertSentCount(1);
        Http::assertSent(
            static fn (Request $request): bool => $request->url() === 'https://feeds.example.test/reuters.rdf'
        );
    }

    public function testDryRunDoesNotWriteRecords(): void
    {
        $this->configureFeedSources();

        Http::fake([
            'https://feeds.example.test/hacker-news.xml' => Http::response(self::rssFeed(), 200),
        ]);

        $this->fetchFeeds(['--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseCount('news_items', 0);
    }

    public function testDuplicateItemsAreSkipped(): void
    {
        $this->configureFeedSources();

        Http::fake([
            'https://feeds.example.test/hacker-news.xml' => Http::response(self::rssFeed(), 200),
        ]);

        $this->fetchFeeds()
            ->assertSuccessful();
        $this->fetchFeeds()
            ->assertSuccessful();

        $this->assertDatabaseCount('news_items', 2);
    }

    public function testValidFeedItemsAreStoredWithProcessingDefaults(): void
    {
        $this->configureFeedSources();

        Http::fake([
            'https://feeds.example.test/hacker-news.xml' => Http::response(self::rssFeed(), 200),
        ]);

        $this->fetchFeeds(['--limit' => 1])
            ->assertSuccessful();

        $this->assertDatabaseCount('news_items', 1);
        $this->assertDatabaseHas('news_items', [
            'source_key' => 'hacker_news',
            'source_name' => 'Hacker News',
            'external_id' => 'hn-1',
            'source_url' => 'https://news.example.test/one',
            'discussion_url' => null,
            'title' => 'First item',
            'processing_status' => 'fetched',
            'translation_status' => 'pending',
            'summary_status' => 'pending',
            'article_content_status' => 'pending',
        ]);

        $item = NewsItem::query()->firstOrFail();

        self::assertNotSame('', $item->identity_hash);
        self::assertNotSame('', $item->content_hash);
    }

    public function testFailedFeedResponseIsLoggedAndDoesNotCrashTheCommand(): void
    {
        $this->configureFeedSources();
        $loggedWarnings = [];

        Log::listen(static function (MessageLogged $message) use (&$loggedWarnings): void {
            if ($message->level === 'warning' && $message->message === 'RSS feed fetch returned unsuccessful HTTP status.') {
                $loggedWarnings[] = $message->context;
            }
        });

        Http::fake([
            'https://feeds.example.test/hacker-news.xml' => Http::response('server error', 500),
        ]);

        $this->fetchFeeds()
            ->assertSuccessful();

        $this->assertDatabaseCount('news_items', 0);
        self::assertSame(500, $loggedWarnings[0]['http_status'] ?? null);
    }

    public function testMalformedFeedIsLoggedAndDoesNotCrashTheCommand(): void
    {
        $this->configureFeedSources();
        $loggedErrors = [];

        Log::listen(static function (MessageLogged $message) use (&$loggedErrors): void {
            if ($message->level === 'error' && $message->message === 'RSS feed fetch failed with an unexpected exception.') {
                $loggedErrors[] = $message->context;
            }
        });

        Http::fake([
            'https://feeds.example.test/hacker-news.xml' => Http::response('<rss><channel><item>', 200),
        ]);

        $this->fetchFeeds()
            ->assertSuccessful();

        $this->assertDatabaseCount('news_items', 0);
        self::assertSame(RuntimeException::class, $loggedErrors[0]['exception_class'] ?? null);
    }

    public function testHackerNewsCommentsDescriptionIsNotStoredAsExcerptAndCommentsUrlIsPreserved(): void
    {
        $this->configureFeedSources();

        Http::fake([
            'https://feeds.example.test/hacker-news.xml' => Http::response(self::hackerNewsFeedWithCommentsDescription(), 200),
        ]);

        $this->fetchFeeds()
            ->assertSuccessful();

        $this->assertDatabaseHas('news_items', [
            'source_key' => 'hacker_news',
            'source_url' => 'https://article.example.test/story',
            'discussion_url' => 'https://news.ycombinator.com/item?id=48248014',
            'title' => 'HN linked article',
            'excerpt' => null,
        ]);
    }

    /**
     * @param array<string, bool|int|string> $parameters
     */
    private function fetchFeeds(array $parameters = []): PendingCommand
    {
        $command = $this->artisan('digestpipe:feeds:fetch', $parameters);

        assert($command instanceof PendingCommand);

        return $command;
    }

    /**
     * @param list<array{key: string, name: string, url: string, language: string, enabled: bool}>|null $sources
     */
    private function configureFeedSources(?array $sources = null): void
    {
        config([
            'digestpipe.feed_sources' => $sources ?? [
                [
                    'key' => 'hacker_news',
                    'name' => 'Hacker News',
                    'url' => 'https://feeds.example.test/hacker-news.xml',
                    'language' => 'en',
                    'enabled' => true,
                ],
            ],
        ]);
    }

    private static function rssFeed(): string
    {
        return <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    <title>Example Feed</title>
                    <item>
                        <guid>hn-1</guid>
                        <title>First item</title>
                        <link>https://news.example.test/one</link>
                        <description>First excerpt</description>
                        <pubDate>Sat, 23 May 2026 12:00:00 GMT</pubDate>
                    </item>
                    <item>
                        <guid>hn-2</guid>
                        <title>Second item</title>
                        <link>https://news.example.test/two</link>
                        <description>Second excerpt</description>
                        <pubDate>Sat, 23 May 2026 13:00:00 GMT</pubDate>
                    </item>
                </channel>
            </rss>
            XML;
    }

    private static function rdfFeed(): string
    {
        return <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
                     xmlns="http://purl.org/rss/1.0/"
                     xmlns:dc="http://purl.org/dc/elements/1.1/">
                <item rdf:about="https://reuters.example.test/top">
                    <title>Reuters item</title>
                    <link>https://reuters.example.test/top</link>
                    <description>Reuters excerpt</description>
                    <dc:date>2026-05-23T12:00:00Z</dc:date>
                </item>
            </rdf:RDF>
            XML;
    }

    private static function hackerNewsFeedWithCommentsDescription(): string
    {
        return <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    <item>
                        <guid>hn-comments-only</guid>
                        <title>HN linked article</title>
                        <link>https://article.example.test/story</link>
                        <comments>https://news.ycombinator.com/item?id=48248014</comments>
                        <description><![CDATA[<a href="https://news.ycombinator.com/item?id=48248014">Comments</a>]]></description>
                    </item>
                </channel>
            </rss>
            XML;
    }
}
