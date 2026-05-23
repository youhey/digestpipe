<?php

namespace App\Feeds;

use Carbon\CarbonImmutable;

/**
 * RSS feedから抽出した単一ニュースitemの正規化済み値です。
 */
class FeedItem
{
    /** Feed内のGUIDやURLなど、source側が提供する識別子です。 */
    public readonly ?string $externalId;

    /** 元記事またはfeed itemのURLです。 */
    public readonly ?string $sourceUrl;

    /** Feed itemのtitleです。 */
    public readonly string $title;

    /** Feed itemのdescriptionから作る短い本文候補です。 */
    public readonly ?string $excerpt;

    /** Feed itemの公開日時です。 */
    public readonly ?CarbonImmutable $publishedAt;

    /**
     * 正規化済みfeed itemを作成します。
     */
    public function __construct(?string $externalId, ?string $sourceUrl, string $title, ?string $excerpt, ?CarbonImmutable $publishedAt)
    {
        $this->externalId = $externalId;
        $this->sourceUrl = $sourceUrl;
        $this->title = $title;
        $this->excerpt = $excerpt;
        $this->publishedAt = $publishedAt;
    }

    /**
     * 同一source内の重複判定に使う安定hashを返します。
     */
    public function identityHash(): string
    {
        $identity = $this->externalId ?? $this->sourceUrl ?? $this->contentHash();

        return hash('sha256', $identity);
    }

    /**
     * 後続処理で内容変化を検知するためのhashを返します。
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
