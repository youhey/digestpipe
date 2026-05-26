<?php

namespace App\Feeds;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

/**
 * RSS 2.0 / RDF feed XML からDigest Itemのアイテム候補を抽出
 */
class RssFeedParser
{
    /**
     * RSS フィード の XML をパースして保存可能なアイテムのリストと失敗した件数を返す
     *
     * @param string $xml
     * @param int|null $limit
     *
     * @return ParsedFeed
     */
    public function parse(string $xml, ?int $limit = null): ParsedFeed
    {
        $dom = new DOMDocument();

        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $dom->loadXML($xml, LIBXML_NOCDATA | LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($loaded === false) {
            throw new RuntimeException('Feed XML could not be parsed.');
        }

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//*[local-name() = "item"]');

        if ($nodes === false) {
            throw new RuntimeException('Feed item XPath query failed.');
        }

        $items = [];
        $failedItemCount = 0;
        $maxItems = $limit ?? PHP_INT_MAX;

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            if (count($items) >= $maxItems) {
                break;
            }

            try {
                $items[] = $this->parseItem($node);
            } catch (UnexpectedValueException) {
                ++$failedItemCount;
            }
        }

        return new ParsedFeed($items, $failedItemCount);
    }

    private function parseItem(DOMElement $item): FeedItem
    {
        $title = $this->text($item, 'title');
        $sourceUrl = $this->text($item, 'link');
        $discussionUrl = $this->text($item, 'comments');
        $externalId = $this->text($item, 'guid') ?? $this->attribute($item, 'about') ?? $sourceUrl;
        $excerpt = $this->normalizeExcerpt($this->text($item, 'description'), $discussionUrl);
        $publishedAt = $this->parseDate(
            $this->text($item, 'pubDate')
            ?? $this->text($item, 'date')
            ?? $this->text($item, 'published')
            ?? $this->text($item, 'updated')
        );

        if ($title === null || $externalId === null) {
            throw new UnexpectedValueException('Feed item is missing required identity fields.');
        }

        return new FeedItem(
            externalId: $externalId,
            sourceUrl: $sourceUrl,
            discussionUrl: $discussionUrl,
            title: $title,
            excerpt: $excerpt,
            publishedAt: $publishedAt,
        );
    }

    private function text(DOMElement $item, string $localName): ?string
    {
        foreach ($item->childNodes as $childNode) {
            if (! $childNode instanceof DOMElement || $childNode->localName !== $localName) {
                continue;
            }

            $value = trim($childNode->textContent);

            return $value === '' ? null : $value;
        }

        return null;
    }

    private function attribute(DOMElement $item, string $localName): ?string
    {
        foreach ($item->attributes as $attribute) {
            if ($attribute->localName !== $localName) {
                continue;
            }

            $value = trim($attribute->value);

            return $value === '' ? null : $value;
        }

        return null;
    }

    private function parseDate(?string $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeExcerpt(?string $value, ?string $discussionUrl): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5));

        if ($text === '') {
            return null;
        }

        if ($this->isCommentsOnlyExcerpt($text, $discussionUrl)) {
            return null;
        }

        $normalizedText = preg_replace('/\s+/', ' ', $text);

        if (! is_string($normalizedText)) {
            return null;
        }

        return $normalizedText;
    }

    private function isCommentsOnlyExcerpt(string $text, ?string $discussionUrl): bool
    {
        if (strcasecmp($text, 'Comments') !== 0) {
            return false;
        }

        return is_string($discussionUrl)
            && str_starts_with($discussionUrl, 'https://news.ycombinator.com/item?');
    }
}
