<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * RSS フィードから取得したニュース記事アイテム
 *
 * @property int $id
 * @property string $source_key
 * @property string $source_name
 * @property string|null $external_id
 * @property string $identity_hash
 * @property string|null $source_url
 * @property string|null $discussion_url
 * @property string $title
 * @property string|null $excerpt
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable $fetched_at
 * @property string $content_hash
 * @property string $selection_status
 * @property int|null $selection_score
 * @property string|null $selection_reason
 * @property array<string, mixed>|null $selection_result
 * @property CarbonImmutable|null $selection_evaluated_at
 * @property string $article_content_status
 * @property string|null $article_content_text
 * @property CarbonImmutable|null $article_content_fetched_at
 * @property string|null $article_content_error
 * @property string $analysis_status
 * @property array<string, mixed>|null $analysis_json
 * @property string|null $analysis_model
 * @property string|null $analysis_error
 * @property CarbonImmutable|null $analyzed_at
 */
class NewsItem extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'source_key',
        'source_name',
        'external_id',
        'identity_hash',
        'source_url',
        'discussion_url',
        'title',
        'excerpt',
        'published_at',
        'fetched_at',
        'content_hash',
        'selection_status',
        'selection_score',
        'selection_reason',
        'selection_result',
        'selection_evaluated_at',
        'article_content_status',
        'article_content_text',
        'article_content_fetched_at',
        'article_content_error',
        'analysis_status',
        'analysis_json',
        'analysis_model',
        'analysis_error',
        'analyzed_at',
    ];

    /**
     * Downstream digestへ渡せるanalysis JSONが完成しているかを返します。
     */
    public function hasCompletedAnalysis(): bool
    {
        return $this->analysis_status === 'completed'
            && is_array($this->analysis_json)
            && $this->analysis_json !== [];
    }

    /**
     * Downstream applicationへ構造化したdigestとして渡せるかを返します。
     */
    public function readyForDigest(): bool
    {
        return $this->hasCompletedAnalysis();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'fetched_at' => 'immutable_datetime',
            'selection_result' => 'array',
            'selection_evaluated_at' => 'immutable_datetime',
            'analysis_json' => 'array',
            'analyzed_at' => 'immutable_datetime',
            'article_content_fetched_at' => 'immutable_datetime',
        ];
    }
}
