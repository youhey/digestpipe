<?php

namespace App\Feeds;

use InvalidArgumentException;
use UnexpectedValueException;

/**
 * 設定から RSS フィードの定義リストを読み込む
 */
class FeedSourceRepository
{
    /**
     * 有効な RSS フィード情報源 を返す
     *
     * `$sourceKey` が指定された場合は該当する情報源だけに絞る
     *
     * @param string|null $sourceKey 取得する情報源を限定したい場合のキー指定
     *
     * @return list<FeedSource>
     */
    public function enabledSources(?string $sourceKey = null): array
    {
        $sources = array_values(array_filter(
            $this->allSources(),
            static fn (FeedSource $source): bool => $source->enabled
        ));

        if ($sourceKey === null) {
            return $sources;
        }

        $filtered = array_values(array_filter(
            $sources,
            static fn (FeedSource $source): bool => $source->key === $sourceKey
        ));

        if ($filtered === []) {
            throw new InvalidArgumentException("Feed source [{$sourceKey}] is not configured or is disabled.");
        }

        return $filtered;
    }

    /**
     * 設定に定義されたすべての RSS フィード情報源を返す
     *
     * @return list<FeedSource>
     */
    public function allSources(): array
    {
        $configuredSources = config('digestpipe.feed_sources', []);

        if (! is_array($configuredSources)) {
            throw new UnexpectedValueException('digestpipe.feed_sources must be an array.');
        }

        $sources = [];

        foreach ($configuredSources as $configuredSource) {
            if (! is_array($configuredSource)) {
                throw new UnexpectedValueException('Each digestpipe feed source must be an array.');
            }

            /** @var array{key?: mixed, name?: mixed, url?: mixed, language?: mixed, enabled?: mixed} $configuredSource */
            $sources[] = FeedSource::fromConfig($configuredSource);
        }

        return $sources;
    }
}
