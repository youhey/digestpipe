<?php

namespace App\Feeds;

use InvalidArgumentException;

/**
 * digestpipe.feed_sourcesで定義されたRSS取得元の正規化済み設定です。
 */
class FeedSource
{
    /** Sourceを一意に識別する設定keyです。 */
    public readonly string $key;

    /** ログや保存レコードで使うsource表示名です。 */
    public readonly string $name;

    /** RSS/RDF feedのURLです。 */
    public readonly string $url;

    /** Feed itemの主な言語です。 */
    public readonly string $language;

    /** 取得対象として有効かどうかを示します。 */
    public readonly bool $enabled;

    /**
     * Feed source設定を作成します。
     */
    public function __construct(string $key, string $name, string $url, string $language, bool $enabled)
    {
        $this->key = $key;
        $this->name = $name;
        $this->url = $url;
        $this->language = $language;
        $this->enabled = $enabled;
    }

    /**
     * Config arrayからfeed sourceを作成します。
     *
     * @param array{key?: mixed, name?: mixed, url?: mixed, language?: mixed, enabled?: mixed} $data
     */
    public static function fromConfig(array $data): self
    {
        return new self(
            key: self::stringValue($data, 'key'),
            name: self::stringValue($data, 'name'),
            url: self::stringValue($data, 'url'),
            language: self::stringValue($data, 'language'),
            enabled: (bool) ($data['enabled'] ?? false),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function stringValue(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Feed source [{$key}] must be a non-empty string.");
        }

        return trim($value);
    }
}
