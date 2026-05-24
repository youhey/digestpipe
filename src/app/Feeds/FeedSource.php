<?php

namespace App\Feeds;

use InvalidArgumentException;

/**
 * RSS フィード情報源
 */
class FeedSource
{
    /** @var string 情報源を一意に識別するキー */
    public readonly string $key;

    /** @var string ログやレコードに保存する情報源の名称 */
    public readonly string $name;

    /** @var string RSS/RDF フィード の URL */
    public readonly string $url;

    /** @var string アイテムの主な言語 */
    public readonly string $language;

    /** @var bool 取得対象として有効かどうか */
    public readonly bool $enabled;

    /**
     * Constructor
     *
     * @param string $key
     * @param string $name
     * @param string $url
     * @param string $language
     * @param bool $enabled
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
     * 設定の連想配列からインスタンスを生成して返す
     *
     * @param array{key?: mixed, name?: mixed, url?: mixed, language?: mixed, enabled?: mixed} $data
     *
     * @return self
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
