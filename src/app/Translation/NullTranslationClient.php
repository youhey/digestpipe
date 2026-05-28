<?php

namespace App\Translation;

/**
 * 翻訳未設定時に安全に失敗する client
 */
class NullTranslationClient implements TranslationClient
{
    /**
     * @throws TranslationException
     */
    public function translate(string $text, string $targetLanguage): string
    {
        throw new TranslationException('Translation is not configured.');
    }
}
