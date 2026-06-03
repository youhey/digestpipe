<?php

namespace App\Feeds;

use App\Models\DigestItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * 正規化したDigest Itemを永続化
 */
class DigestItemIngestor
{
    /**
     * Digest Itemのアイテムを DB に保存
     *
     * @param FeedSource $source RSS フィード情報源
     * @param list<FeedItem> $items Digest Itemのリスト
     * @param bool $dryRun dry-run フラグ
     *
     * @return IngestFeedItemsResult
     */
    public function ingest(FeedSource $source, array $items, bool $dryRun): IngestFeedItemsResult
    {
        $createdCount = 0;
        $skippedDuplicateCount = 0;
        $createdDigestItemIds = [];
        $fetchedAt = CarbonImmutable::now();

        foreach ($items as $item) {
            $identityHash = $item->identityHash();
            $exists = DB::table('digest_items')
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

            $digestItem = DigestItem::query()->create([
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
                'selection_status' => 'pending',
                'article_content_status' => 'pending',
                'analysis_status' => 'pending',
            ]);

            ++$createdCount;
            $createdDigestItemIds[] = $digestItem->id;
        }

        return new IngestFeedItemsResult($createdCount, $skippedDuplicateCount, $createdDigestItemIds);
    }
}
