<?php

namespace App\Articles;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;

/**
 * HTMLから本文候補を決定的なDOM処理で抽出します。
 */
class ArticleTextExtractor
{
    /**
     * HTMLから記事本文を抽出します。
     */
    public function extract(string $html): ExtractedArticleContent
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($loaded === false) {
            throw new ArticleContentExtractionException('Article HTML could not be parsed.');
        }

        $xpath = new DOMXPath($dom);
        $this->removeNoisyNodes($xpath);

        $container = $this->preferredContainer($xpath);
        $text = $this->extractText($container, $xpath);
        $text = $this->normalizeText($text);
        $text = $this->limitText($text);

        if (strlen($text) < $this->minChars()) {
            throw new ArticleContentExtractionException('Extracted article text was too short.');
        }

        return new ExtractedArticleContent($text);
    }

    private function removeNoisyNodes(DOMXPath $xpath): void
    {
        $nodes = $xpath->query('.//*[self::script or self::style or self::noscript or self::svg or self::nav or self::header or self::footer or self::form or self::aside or self::iframe]');

        if ($nodes === false) {
            throw new RuntimeException('Noise node XPath query failed.');
        }

        foreach ($nodes as $node) {
            if ($node instanceof DOMNode && $node->parentNode !== null) {
                $node->parentNode->removeChild($node);
            }
        }
    }

    private function preferredContainer(DOMXPath $xpath): DOMNode
    {
        foreach (['//article', '//main', '//body'] as $query) {
            $nodes = $xpath->query($query);

            if ($nodes === false || $nodes->length === 0) {
                continue;
            }

            $node = $nodes->item(0);

            if ($node instanceof DOMNode) {
                return $node;
            }
        }

        throw new ArticleContentExtractionException('Article HTML did not include a readable container.');
    }

    private function extractText(DOMNode $container, DOMXPath $xpath): string
    {
        $nodes = $xpath->query('.//*[self::h1 or self::h2 or self::h3 or self::p or self::li or self::blockquote]', $container);

        if ($nodes === false) {
            throw new RuntimeException('Article text XPath query failed.');
        }

        $lines = [];

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $line = $this->normalizeLine($node->textContent);

            if ($line === null) {
                continue;
            }

            $lines[] = $line;
        }

        if ($lines === []) {
            return $container->textContent;
        }

        return implode("\n", $lines);
    }

    private function normalizeLine(string $value): ?string
    {
        $line = preg_replace('/\s+/', ' ', trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5)));

        if (! is_string($line) || $line === '') {
            return null;
        }

        return $line;
    }

    private function normalizeText(string $value): string
    {
        $splitLines = preg_split('/\R/u', $value);

        if ($splitLines === false) {
            $splitLines = [];
        }

        $lines = array_filter(array_map(
            fn (string $line): ?string => $this->normalizeLine($line),
            $splitLines
        ), static fn (?string $line): bool => $line !== null);

        return trim(implode("\n\n", $lines));
    }

    private function limitText(string $value): string
    {
        return substr($value, 0, $this->maxChars());
    }

    private function minChars(): int
    {
        $value = config('digestpipe.content.min_chars');

        return is_int($value) && $value > 0 ? $value : 200;
    }

    private function maxChars(): int
    {
        $value = config('digestpipe.content.max_chars');

        return is_int($value) && $value > 0 ? $value : 8000;
    }
}
