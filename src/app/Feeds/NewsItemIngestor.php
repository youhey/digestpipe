<?php

namespace App\Feeds;

use App\Models\NewsItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * 正規化済みfeed itemをnews_items tableへ重複なく保存します。
 */
class NewsItemIngestor
{
    /**
     * Feed itemを保存し、作成数と重複skip数を返します。
     *
     * @param list<FeedItem> $items
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
                'processing_status' => 'fetched',
                'translation_status' => 'pending',
                'summary_status' => 'pending',
                'analysis_status' => 'pending',
                'article_content_status' => 'pending',
                'error_message' => null,
            ]);

            ++$createdCount;
        }

        return new IngestFeedItemsResult($createdCount, $skippedDuplicateCount);
    }
}
