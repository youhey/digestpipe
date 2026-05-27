<?php

namespace App\Feeds;

use App\Models\FeedSource as FeedSourceModel;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * DBから RSS フィードの定義リストを読み込む
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
        $filtered = array_values(array_filter(
            $this->allSources(),
            static fn (FeedSource $source): bool => $source->enabled
                && ($sourceKey === null || $source->key === $sourceKey)
        ));

        if ($sourceKey !== null && $filtered === []) {
            throw new InvalidArgumentException("Feed source [{$sourceKey}] is not configured or is disabled.");
        }

        return $filtered;
    }

    /**
     * 分析処理対象として有効な RSS フィード情報源 を返す
     *
     * @return list<FeedSource>
     */
    public function analysisEnabledSources(): array
    {
        return array_values(array_filter(
            $this->allSources(),
            static fn (FeedSource $source): bool => $source->enabled && $source->analysisEnabled
        ));
    }

    /**
     * DBに定義されたすべての RSS フィード情報源を返す
     *
     * @return list<FeedSource>
     */
    public function allSources(): array
    {
        $sources = [];

        foreach (DB::table('feed_sources')->orderBy('sort_order')->orderBy('id')->get() as $row) {
            /** @var array<string, mixed> $attributes */
            $attributes = get_object_vars($row);
            $model = (new FeedSourceModel())->newFromBuilder($attributes);
            $sources[] = FeedSource::fromModel($model);
        }

        return $sources;
    }
}
