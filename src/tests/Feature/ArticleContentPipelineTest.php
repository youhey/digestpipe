<?php

namespace Tests\Feature;

use App\Articles\ArticleTextExtractor;
use App\Items\DigestItemTextSelector;
use App\Jobs\FetchDigestItemArticleContentJob;
use App\Models\DigestItem;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * @internal
 */
class ArticleContentPipelineTest extends TestCase
{
    use RefreshDatabase;

    private int $digestItemSequence = 0;

    public function testFetchJobStoresExtractedArticleTextFromHtml(): void
    {
        config([
            'digestpipe.content.min_chars' => 80,
        ]);

        $item = $this->createDigestItem([
            'article_content_status' => 'queued',
            'source_url' => 'https://article.example.test/story',
        ]);

        Http::fake([
            'https://article.example.test/story' => Http::response($this->articleHtml(), 200, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]),
        ]);

        $extractor = $this->articleTextExtractor();
        (new FetchDigestItemArticleContentJob($item->id))->handle($extractor);

        $item->refresh();

        self::assertSame('completed', $item->article_content_status);
        self::assertNotNull($item->article_content_text);
        self::assertStringContainsString('A meaningful article title', $item->article_content_text);
        self::assertStringContainsString('This is the first paragraph with useful article content', $item->article_content_text);
        self::assertStringNotContainsString('Navigation should be removed', $item->article_content_text);
        self::assertNotNull($item->article_content_fetched_at);
    }

    public function testFetchJobSkipsNonHtmlResponses(): void
    {
        $item = $this->createDigestItem([
            'article_content_status' => 'queued',
        ]);

        Http::fake([
            $item->source_url => Http::response('{"ok":true}', 200, [
                'Content-Type' => 'application/json',
            ]),
        ]);

        $extractor = $this->articleTextExtractor();
        (new FetchDigestItemArticleContentJob($item->id))->handle($extractor);

        $this->assertDatabaseHas('digest_items', [
            'id' => $item->id,
            'article_content_status' => 'skipped',
            'article_content_error' => 'Article response was not HTML.',
        ]);
    }

    public function testFetchJobHandlesHttpFailuresSafely(): void
    {
        $item = $this->createDigestItem([
            'article_content_status' => 'queued',
        ]);

        Http::fake([
            $item->source_url => Http::response('server error', 500, [
                'Content-Type' => 'text/html',
            ]),
        ]);

        $extractor = $this->articleTextExtractor();
        (new FetchDigestItemArticleContentJob($item->id))->handle($extractor);

        $this->assertDatabaseHas('digest_items', [
            'id' => $item->id,
            'article_content_status' => 'failed',
            'article_content_error' => 'Article fetch failed with HTTP status 500.',
        ]);
    }

    public function testFetchJobIsIdempotentWhenContentIsAlreadyCompleted(): void
    {
        $item = $this->createDigestItem([
            'article_content_status' => 'completed',
            'article_content_text' => 'Existing article content.',
        ]);

        Http::fake();

        $extractor = $this->articleTextExtractor();
        (new FetchDigestItemArticleContentJob($item->id))->handle($extractor);

        Http::assertNothingSent();
        $this->assertDatabaseHas('digest_items', [
            'id' => $item->id,
            'article_content_status' => 'completed',
            'article_content_text' => 'Existing article content.',
        ]);
    }

    public function testTextSelectorPrefersArticleContentText(): void
    {
        $item = $this->createDigestItem([
            'title' => 'Original title',
            'excerpt' => 'RSS excerpt',
            'article_content_text' => 'Extracted article text',
        ]);

        $text = (new DigestItemTextSelector())->bodyText($item);

        self::assertSame('Extracted article text', $text);
    }

    public function testTextSelectorStripsRawHtml(): void
    {
        $item = $this->createDigestItem([
            'title' => 'Original title',
            'excerpt' => '<p>RSS <strong>excerpt</strong></p>',
            'article_content_text' => '<article><p>Extracted <strong>article</strong> text</p></article>',
        ]);

        $text = (new DigestItemTextSelector())->bodyText($item);

        self::assertSame('Extracted article text', $text);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createDigestItem(array $attributes = []): DigestItem
    {
        ++$this->digestItemSequence;
        $sequence = $this->digestItemSequence;

        return DigestItem::query()->create(array_merge([
            'source_key' => 'example',
            'source_name' => 'Example Source',
            'external_id' => 'article-external-' . $sequence,
            'identity_hash' => hash('sha256', 'article-external-' . $sequence),
            'source_url' => 'https://article.example.test/' . $sequence,
            'discussion_url' => null,
            'title' => 'Example title ' . $sequence,
            'excerpt' => 'Example excerpt ' . $sequence,
            'published_at' => CarbonImmutable::parse('2026-05-23 12:00:00'),
            'fetched_at' => CarbonImmutable::parse('2026-05-23 12:05:00'),
            'content_hash' => hash('sha256', 'article-content-' . $sequence),
            'article_content_status' => 'pending',
            'article_content_error' => null,
            'analysis_status' => 'pending',
        ], $attributes));
    }

    private function articleHtml(): string
    {
        return <<<'HTML'
            <html>
                <body>
                    <nav>Navigation should be removed</nav>
                    <article>
                        <h1>A meaningful article title</h1>
                        <p>This is the first paragraph with useful article content and enough text for extraction.</p>
                        <p>This is the second paragraph with additional details for deterministic parsing tests.</p>
                    </article>
                    <script>console.log('noise');</script>
                </body>
            </html>
            HTML;
    }

    private function articleTextExtractor(): ArticleTextExtractor
    {
        return $this->app->make(ArticleTextExtractor::class);
    }
}
