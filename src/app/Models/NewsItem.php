<?php

namespace App\Models;

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
 * @property string $title
 * @property string|null $excerpt
 * @property string $content_hash
 * @property string $processing_status
 * @property string $translation_status
 * @property string $summary_status
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
        'title',
        'excerpt',
        'published_at',
        'fetched_at',
        'content_hash',
        'processing_status',
        'translation_status',
        'summary_status',
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'fetched_at' => 'immutable_datetime',
            'translation_started_at' => 'immutable_datetime',
            'translation_completed_at' => 'immutable_datetime',
            'summary_started_at' => 'immutable_datetime',
            'summary_completed_at' => 'immutable_datetime',
        ];
    }
}
