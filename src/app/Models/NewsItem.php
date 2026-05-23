<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RSS ingestionで取得したニュースitemを表すEloquent modelです。
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
        ];
    }
}
