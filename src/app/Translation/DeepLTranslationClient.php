<?php

namespace App\Translation;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * DeepL API Free を使う翻訳 client
 */
class DeepLTranslationClient implements TranslationClient
{
    private const ENDPOINT = 'https://api-free.deepl.com/v2/translate';

    /**
     * DeepL API で text を翻訳します。
     *
     * @throws TranslationException
     */
    public function translate(string $text, string $targetLanguage): string
    {
        $apiKey = config('digestpipe.deepl.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new TranslationException('Translation is not configured.');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'DeepL-Auth-Key ' . trim($apiKey),
            ])
                ->acceptJson()
                ->asJson()
                ->timeout(30)
                ->post(self::ENDPOINT, [
                    'text' => [$text],
                    'target_lang' => $targetLanguage,
                ]);
        } catch (ConnectionException $exception) {
            throw new TranslationException('Translation request failed.', previous: $exception);
        }

        if ($response->failed()) {
            throw new TranslationException('Translation request failed.');
        }

        $translated = $response->json('translations.0.text');

        if (! is_string($translated) || trim($translated) === '') {
            throw new TranslationException('Translation response was invalid.');
        }

        return $translated;
    }
}
