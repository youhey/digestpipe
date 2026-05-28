<?php

namespace Tests\Feature;

use App\Translation\DeepLTranslationClient;
use App\Translation\DigestItemTranslationService;
use App\Translation\TranslationClient;
use App\Translation\TranslationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * @internal
 */
class DigestItemTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function testFakeTranslationClientReturnsTranslatedText(): void
    {
        config([
            'digestpipe.translation.driver' => 'fake',
            'digestpipe.translation.target_language' => 'JA',
        ]);

        $result = app(DigestItemTranslationService::class)->translateText('Hello world');

        self::assertNotNull($result);
        self::assertSame('[JA] Hello world', $result->text);
        self::assertFalse($result->truncated);
    }

    public function testTranslationServiceSkipsEmptyText(): void
    {
        config(['digestpipe.translation.driver' => 'fake']);

        self::assertNull(app(DigestItemTranslationService::class)->translateText(''));
        self::assertNull(app(DigestItemTranslationService::class)->translateText('N/A'));
    }

    public function testTranslationServiceTruncatesLongText(): void
    {
        config([
            'digestpipe.translation.driver' => 'fake',
            'digestpipe.translation.max_chars' => 5,
        ]);

        $result = app(DigestItemTranslationService::class)->translateText('1234567890');

        self::assertNotNull($result);
        self::assertSame('[JA] 12345', $result->text);
        self::assertTrue($result->truncated);
    }

    public function testDeepLClientSendsHeaderAuthenticatedTranslateRequest(): void
    {
        config([
            'digestpipe.deepl.api_key' => 'test-deepl-key',
        ]);
        Http::fake([
            'https://api-free.deepl.com/v2/translate' => Http::response([
                'translations' => [
                    ['text' => 'こんにちは'],
                ],
            ], 200),
        ]);

        $translated = app(DeepLTranslationClient::class)->translate('Hello', 'JA');

        self::assertSame('こんにちは', $translated);
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api-free.deepl.com/v2/translate'
                && $request->hasHeader('Authorization', 'DeepL-Auth-Key test-deepl-key')
                && ($payload['target_lang'] ?? null) === 'JA'
                && ($payload['text'] ?? null) === ['Hello'];
        });
    }

    public function testNullTranslationClientFailsSafely(): void
    {
        config(['digestpipe.translation.driver' => 'none']);

        $this->expectException(TranslationException::class);

        app(TranslationClient::class)->translate('Hello', 'JA');
    }
}
