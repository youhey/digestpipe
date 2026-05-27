<?php

namespace App\Feeds;

use App\Models\FeedSource as FeedSourceModel;
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

    /** @var bool 分析処理の対象として有効かどうか */
    public readonly bool $analysisEnabled;

    /** @var string 情報源の運用 tier */
    public readonly string $tier;

    /** @var string 情報源の分類 */
    public readonly string $category;

    /** @var int 表示・処理順 */
    public readonly int $sortOrder;

    /**
     * Constructor
     *
     * @param string $key
     * @param string $name
     * @param string $url
     * @param string $language
     * @param bool $enabled
     * @param bool $analysisEnabled
     * @param string $tier
     * @param string $category
     * @param int $sortOrder
     */
    public function __construct(string $key, string $name, string $url, string $language, bool $enabled, bool $analysisEnabled, string $tier, string $category, int $sortOrder)
    {
        $this->key = $key;
        $this->name = $name;
        $this->url = $url;
        $this->language = $language;
        $this->enabled = $enabled;
        $this->analysisEnabled = $analysisEnabled;
        $this->tier = $tier;
        $this->category = $category;
        $this->sortOrder = $sortOrder;
    }

    /**
     * Feed Source model から runtime 用インスタンスを生成して返す
     *
     * @param FeedSourceModel $model
     *
     * @return self
     */
    public static function fromModel(FeedSourceModel $model): self
    {
        return new self(
            key: self::stringValue($model->key, 'key'),
            name: self::stringValue($model->name, 'name'),
            url: self::stringValue($model->url, 'url'),
            language: self::stringValue($model->language, 'language'),
            enabled: $model->enabled,
            analysisEnabled: $model->analysis_enabled,
            tier: self::stringValue($model->tier, 'tier'),
            category: self::stringValue($model->category, 'category'),
            sortOrder: $model->sort_order,
        );
    }

    private static function stringValue(mixed $value, string $key): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Feed source [{$key}] must be a non-empty string.");
        }

        return trim($value);
    }
}
