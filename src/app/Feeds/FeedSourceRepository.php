<?php

namespace App\Feeds;

use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Laravel configからRSS feed source定義を読み出します。
 */
class FeedSourceRepository
{
    /**
     * 有効なfeed sourceを返します。source key指定時は該当sourceだけに絞ります。
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
     * Configに定義されたすべてのfeed sourceを返します。
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
