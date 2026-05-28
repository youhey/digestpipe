<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidationValidator;

/**
 * DB 管理される selection keyword
 *
 * @property int $id
 * @property string $keyword
 * @property string $type
 * @property int $score
 * @property bool $enabled
 * @property string $locale
 * @property string|null $category
 * @property string|null $notes
 * @property int $sort_order
 * @property string $match_mode
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class SelectionKeyword extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = [
        'enabled' => true,
        'locale' => 'any',
        'sort_order' => 100,
        'match_mode' => 'contains',
    ];

    /** @var list<string> */
    protected $fillable = [
        'keyword',
        'type',
        'score',
        'enabled',
        'locale',
        'category',
        'notes',
        'sort_order',
        'match_mode',
    ];

    /**
     * Selection Keyword の入力値が処理可能な範囲に収まっているか検証します。
     *
     * @throws ValidationException
     */
    public function validate(): void
    {
        Validator::make($this->attributesToArray(), [
            'keyword' => [
                'required',
                'string',
                'max:255',
                Rule::unique('selection_keywords', 'keyword')
                    ->where('type', $this->type)
                    ->ignore($this->id),
            ],
            'type' => ['required', Rule::in(['positive', 'negative'])],
            'score' => ['required', 'integer', 'not_in:0'],
            'enabled' => ['required', 'boolean'],
            'locale' => ['required', Rule::in(['any', 'en', 'ja'])],
            'category' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
            'match_mode' => ['required', Rule::in(['contains', 'word_boundary', 'exact_phrase'])],
        ])->after(function (ValidationValidator $validator): void {
            if ($this->type === 'positive' && $this->score <= 0) {
                $validator->errors()->add('score', 'positive selection keyword scores must be greater than zero.');
            }

            if ($this->type === 'negative' && $this->score >= 0) {
                $validator->errors()->add('score', 'negative selection keyword scores must be less than zero.');
            }
        })->validate();
    }

    /**
     * 保存前に Selection Keyword を正規化して検証します。
     */
    protected static function booted(): void
    {
        static::saving(static function (SelectionKeyword $keyword): void {
            $keyword->keyword = trim($keyword->keyword);
            $keyword->category = $keyword->category === null ? null : trim($keyword->category);
            $keyword->notes = $keyword->notes === null ? null : trim($keyword->notes);
            $keyword->match_mode = trim($keyword->match_mode);

            if ($keyword->category === '') {
                $keyword->category = null;
            }

            if ($keyword->notes === '') {
                $keyword->notes = null;
            }

            $keyword->validate();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'enabled' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
