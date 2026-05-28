<?php

namespace App\Translation;

/**
 * Digest Item review UI 用の一時翻訳処理を提供します。
 */
class DigestItemTranslationService
{
    private TranslationClient $client;

    /**
     * Constructor
     *
     * @param TranslationClient $client
     */
    public function __construct(TranslationClient $client)
    {
        $this->client = $client;
    }

    /**
     * 文字列を設定済みの対象言語へ翻訳します。
     *
     * @throws TranslationException
     */
    public function translateText(?string $text): ?TranslationResult
    {
        $input = $this->cleanText($text);

        if ($input === null) {
            return null;
        }

        $maxChars = $this->maxChars();
        $truncated = mb_strlen($input) > $maxChars;
        $source = $truncated ? mb_substr($input, 0, $maxChars) : $input;

        return new TranslationResult(
            $this->client->translate($source, $this->targetLanguage()),
            $truncated,
        );
    }

    /**
     * 空ではない文字列 list を翻訳します。
     *
     * @param list<string> $texts
     *
     * @return list<TranslationResult>
     *
     * @throws TranslationException
     */
    public function translateList(array $texts): array
    {
        $results = [];

        foreach ($texts as $text) {
            $result = $this->translateText($text);

            if ($result !== null) {
                $results[] = $result;
            }
        }

        return $results;
    }

    public function maxChars(): int
    {
        $configured = config('digestpipe.translation.max_chars', 8000);

        return max(1, is_int($configured) ? $configured : 8000);
    }

    private function targetLanguage(): string
    {
        $configured = config('digestpipe.translation.target_language', 'JA');

        return is_string($configured) && trim($configured) !== '' ? trim($configured) : 'JA';
    }

    private function cleanText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $trimmed = trim($text);

        if ($trimmed === '' || $trimmed === 'N/A') {
            return null;
        }

        return $trimmed;
    }
}
