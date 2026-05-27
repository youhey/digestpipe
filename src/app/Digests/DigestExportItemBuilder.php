<?php

namespace App\Digests;

use App\Feeds\FeedSourceRepository;
use App\Models\DigestItem;
use Carbon\CarbonInterface;

/**
 * Digest Itemから構造化 digest export 用の配列を生成
 */
class DigestExportItemBuilder
{
    private readonly FeedSourceRepository $sources;

    /** @var array<string, string>|null */
    private ?array $feedUrls = null;

    /**
     * Constructor
     *
     * @param FeedSourceRepository $sources
     */
    public function __construct(FeedSourceRepository $sources)
    {
        $this->sources = $sources;
    }

    /**
     * Digest Itemを downstream 向けの digest record に変換する
     *
     * @param DigestItem $item
     *
     * @return array<string, mixed>
     */
    public function build(DigestItem $item): array
    {
        return [
            'id' => $item->id,
            'source' => [
                'key' => $item->source_key,
                'name' => $item->source_name,
                'feed_url' => $this->feedUrls()[$item->source_key] ?? null,
            ],
            'article' => [
                'title' => $item->title,
                'url' => $item->source_url,
                'discussion_url' => $item->discussion_url,
                'published_at' => $this->timestamp($item->published_at),
                'fetched_at' => $this->timestamp($item->fetched_at),
            ],
            'selection' => [
                'status' => $item->selection_status,
                'score' => $item->selection_score,
            ],
            'analysis' => $item->analysis_json,
            'processing' => [
                'analysis_model' => $item->analysis_model,
                'analyzed_at' => $this->timestamp($item->analyzed_at),
            ],
        ];
    }

    /**
     * RSS フィード情報源の URL を source key で参照できる配列として返す
     *
     * @return array<string, string>
     */
    private function feedUrls(): array
    {
        if ($this->feedUrls !== null) {
            return $this->feedUrls;
        }

        $urls = [];

        foreach ($this->sources->allSources() as $source) {
            $urls[$source->key] = $source->url;
        }

        $this->feedUrls = $urls;

        return $this->feedUrls;
    }

    private function timestamp(mixed $value): ?string
    {
        if (! $value instanceof CarbonInterface) {
            return null;
        }

        return $value->toJSON();
    }
}
