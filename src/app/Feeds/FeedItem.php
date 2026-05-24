<?php

namespace App\Feeds;

use Carbon\CarbonImmutable;

/**
 * RSS フィードから単一のニュースを取り出して正規化したデータ構造
 */
class FeedItem
{
    /** @var string|null RSS フィードの GUID や URL など 情報提供元が提供する識別子 */
    public readonly ?string $externalId;

    /** @var string|null 元記事または RSS フィードのURL */
    public readonly ?string $sourceUrl;

    /** @var string|null Discussion または Comment Thread の URL */
    public readonly ?string $discussionUrl;

    /** @var string RSS フィードのタイトル */
    public readonly string $title;

    /** @var string|null RSS フィードの description (短縮版の本文候補) */
    public readonly ?string $excerpt;

    /** @var CarbonImmutable|null RSS フィードの公開日時 */
    public readonly ?CarbonImmutable $publishedAt;

    /**
     * Constructor
     *
     * @param string|null $externalId
     * @param string|null $sourceUrl
     * @param string|null $discussionUrl
     * @param string $title
     * @param string|null $excerpt
     * @param CarbonImmutable|null $publishedAt
     */
    public function __construct(?string $externalId, ?string $sourceUrl, ?string $discussionUrl, string $title, ?string $excerpt, ?CarbonImmutable $publishedAt)
    {
        $this->externalId = $externalId;
        $this->sourceUrl = $sourceUrl;
        $this->discussionUrl = $discussionUrl;
        $this->title = $title;
        $this->excerpt = $excerpt;
        $this->publishedAt = $publishedAt;
    }

    /**
     * 情報源の同一提供元で重複を判定するハッシュ値を返す
     *
     * @return string
     */
    public function identityHash(): string
    {
        $identity = $this->externalId ?? $this->sourceUrl ?? $this->contentHash();

        return hash('sha256', $identity);
    }

    /**
     * 後続処理で内容変化を検知するためのハッシュ値を返す
     *
     * @return string
     */
    public function contentHash(): string
    {
        return hash('sha256', implode('|', [
            $this->title,
            $this->sourceUrl ?? '',
            $this->excerpt ?? '',
            $this->publishedAt?->toIso8601String() ?? '',
        ]));
    }
}
