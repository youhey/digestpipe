<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * digestpipe Artisan command の実行記録
 *
 * @property int $id
 * @property string $command_name
 * @property array<string, mixed> $command_arguments
 * @property string $status
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $finished_at
 * @property int|null $duration_ms
 * @property array<string, mixed>|null $result_summary
 * @property string|null $error_message
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class DigestpipeCommandRun extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'command_name',
        'command_arguments',
        'status',
        'started_at',
        'finished_at',
        'duration_ms',
        'result_summary',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'command_arguments' => 'array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'duration_ms' => 'integer',
            'result_summary' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
