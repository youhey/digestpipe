<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidationValidator;

/**
 * DB 管理される RSS フィード情報源
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $url
 * @property string $language
 * @property bool $enabled
 * @property bool $analysis_enabled
 * @property string $tier
 * @property string $category
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class FeedSource extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = [
        'sort_order' => 100,
    ];

    /** @var list<string> */
    protected $fillable = [
        'key',
        'name',
        'url',
        'language',
        'enabled',
        'analysis_enabled',
        'tier',
        'category',
        'sort_order',
    ];

    /**
     * Feed Source の入力値が処理可能な範囲に収まっているか検証します。
     *
     * @throws ValidationException
     */
    public function validate(): void
    {
        Validator::make($this->attributesToArray(), [
            'key' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/',
                Rule::unique('feed_sources', 'key')->ignore($this->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'url' => [
                'required',
                'string',
                'url',
                'max:2048',
                'regex:/^https?:\/\//',
                Rule::unique('feed_sources', 'url')->ignore($this->id),
            ],
            'language' => ['required', Rule::in(['en', 'ja'])],
            'enabled' => ['required', 'boolean'],
            'analysis_enabled' => ['required', 'boolean'],
            'tier' => ['required', Rule::in(['core', 'candidate'])],
            'category' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
        ])->after(function (ValidationValidator $validator): void {
            if (! $this->enabled && $this->analysis_enabled) {
                $validator->errors()->add('analysis_enabled', 'analysis_enabled cannot be true when enabled is false.');
            }
        })->validate();
    }

    /**
     * 保存前に Feed Source の一貫性を検証します。
     */
    protected static function booted(): void
    {
        static::saving(static function (FeedSource $feedSource): void {
            $feedSource->validate();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'analysis_enabled' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
