<?php

namespace App\Feeds;

use App\Models\NewsItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * 正規化したニュース記事アイテムを永続化
 */
class NewsItemIngestor
{
    /**
     * ニュース記事のアイテムを DB に保存
     *
     * @param FeedSource $source RSS フィード情報源
     * @param list<FeedItem> $items ニュース記事アイテムのリスト
     * @param bool $dryRun dry-run フラグ
     *
     * @return IngestFeedItemsResult
     */
    public function ingest(FeedSource $source, array $items, bool $dryRun): IngestFeedItemsResult
    {
        $createdCount = 0;
        $skippedDuplicateCount = 0;
        $fetchedAt = CarbonImmutable::now();

        foreach ($items as $item) {
            $identityHash = $item->identityHash();
            $exists = DB::table('news_items')
                ->where('source_key', $source->key)
                ->where('identity_hash', $identityHash)
                ->exists();

            if ($exists) {
                ++$skippedDuplicateCount;

                continue;
            }

            if ($dryRun) {
                ++$createdCount;

                continue;
            }

            NewsItem::query()->create([
                'source_key' => $source->key,
                'source_name' => $source->name,
                'external_id' => $item->externalId,
                'identity_hash' => $identityHash,
                'source_url' => $item->sourceUrl,
                'discussion_url' => $item->discussionUrl,
                'title' => $item->title,
                'excerpt' => $item->excerpt,
                'published_at' => $item->publishedAt,
                'fetched_at' => $fetchedAt,
                'content_hash' => $item->contentHash(),
                'article_content_status' => 'pending',
                'analysis_status' => 'pending',
            ]);

            ++$createdCount;
        }

        return new IngestFeedItemsResult($createdCount, $skippedDuplicateCount);
    }
}
