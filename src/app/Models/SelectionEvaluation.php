<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Digest Item の selection 評価履歴
 *
 * @property int $id
 * @property int $digest_item_id
 * @property string $source_key
 * @property string $phase
 * @property string $status
 * @property int $score
 * @property string|null $reason
 * @property list<string> $matched_positive_keywords
 * @property list<string> $matched_negative_keywords
 * @property array<string, mixed> $input_summary
 * @property array<string, mixed>|null $selection_config_summary
 * @property CarbonImmutable $evaluated_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class SelectionEvaluation extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'digest_item_id',
        'source_key',
        'phase',
        'status',
        'score',
        'reason',
        'matched_positive_keywords',
        'matched_negative_keywords',
        'input_summary',
        'selection_config_summary',
        'evaluated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'matched_positive_keywords' => 'array',
            'matched_negative_keywords' => 'array',
            'input_summary' => 'array',
            'selection_config_summary' => 'array',
            'evaluated_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
