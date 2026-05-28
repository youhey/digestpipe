<?php

namespace App\Translation;

/**
 * 外部翻訳 provider への最小インターフェース
 */
interface TranslationClient
{
    /**
     * 指定言語へ text を翻訳します。
     *
     * @throws TranslationException
     */
    public function translate(string $text, string $targetLanguage): string;
}
