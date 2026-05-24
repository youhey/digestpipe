<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * RSS ingestionで取得したニュースitemを表すEloquent modelです。
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
 * @property string $content_hash
 * @property string $processing_status
 * @property string $translation_status
 * @property string $summary_status
 * @property string $analysis_status
 * @property array<string, mixed>|null $analysis_json
 * @property string|null $analysis_model
 * @property string|null $analysis_error
 * @property CarbonImmutable|null $analyzed_at
 * @property string $article_content_status
 * @property string|null $article_content_text
 * @property string|null $article_content_error
 * @property string|null $translated_title
 * @property string|null $translated_description
 * @property string|null $summary
 * @property string|null $processing_error
 * @property string|null $error_message
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
        'processing_status',
        'translation_status',
        'summary_status',
        'analysis_status',
        'analysis_json',
        'analysis_model',
        'analysis_error',
        'analyzed_at',
        'article_content_status',
        'article_content_text',
        'article_content_fetched_at',
        'article_content_error',
        'translated_title',
        'translated_description',
        'summary',
        'processing_error',
        'translation_started_at',
        'translation_completed_at',
        'summary_started_at',
        'summary_completed_at',
        'error_message',
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'fetched_at' => 'immutable_datetime',
            'analysis_json' => 'array',
            'analyzed_at' => 'immutable_datetime',
            'article_content_fetched_at' => 'immutable_datetime',
            'translation_started_at' => 'immutable_datetime',
            'translation_completed_at' => 'immutable_datetime',
            'summary_started_at' => 'immutable_datetime',
            'summary_completed_at' => 'immutable_datetime',
        ];
    }
}
