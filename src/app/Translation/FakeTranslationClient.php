<?php

namespace App\Translation;

/**
 * テスト用の deterministic な翻訳 client
 */
class FakeTranslationClient implements TranslationClient
{
    /**
     * 翻訳済みであることが分かる固定 prefix を付けて返します。
     */
    public function translate(string $text, string $targetLanguage): string
    {
        return "[{$targetLanguage}] {$text}";
    }
}
