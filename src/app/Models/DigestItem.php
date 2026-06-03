<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * RSS フィードから取得したDigest Item
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
 * @property CarbonImmutable|null $article_content_queued_at
 * @property CarbonImmutable|null $article_content_started_at
 * @property string|null $article_content_text
 * @property CarbonImmutable|null $article_content_fetched_at
 * @property CarbonImmutable|null $article_content_skipped_at
 * @property CarbonImmutable|null $article_content_failed_at
 * @property string|null $article_content_error
 * @property string $analysis_status
 * @property CarbonImmutable|null $analysis_queued_at
 * @property CarbonImmutable|null $analysis_started_at
 * @property array<string, mixed>|null $analysis_json
 * @property string|null $analysis_model
 * @property string|null $analysis_error
 * @property CarbonImmutable|null $analyzed_at
 * @property CarbonImmutable|null $analysis_completed_at
 * @property CarbonImmutable|null $analysis_skipped_at
 * @property CarbonImmutable|null $analysis_failed_at
 * @property int|null $manual_rating
 * @property CarbonImmutable|null $manual_rated_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class DigestItem extends Model
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
        'article_content_queued_at',
        'article_content_started_at',
        'article_content_text',
        'article_content_fetched_at',
        'article_content_skipped_at',
        'article_content_failed_at',
        'article_content_error',
        'analysis_status',
        'analysis_queued_at',
        'analysis_started_at',
        'analysis_json',
        'analysis_model',
        'analysis_error',
        'analyzed_at',
        'analysis_completed_at',
        'analysis_skipped_at',
        'analysis_failed_at',
        'manual_rating',
        'manual_rated_at',
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
     * Manual rating が設定済みかを返します。
     */
    public function isManuallyRated(): bool
    {
        return $this->manual_rating !== null;
    }

    /**
     * Manual rating が Bad かを返します。
     */
    public function isManuallyBad(): bool
    {
        return $this->manual_rating === -1;
    }

    /**
     * Good rating の star 数を返します。
     */
    public function manualGoodStars(): ?int
    {
        if ($this->manual_rating === null || $this->manual_rating < 1) {
            return null;
        }

        return $this->manual_rating;
    }

    /**
     * Manual rating を設定します。
     */
    public function setManualRating(int $rating): void
    {
        self::validateManualRating($rating);

        $this->manual_rating = $rating;
        $this->manual_rated_at = CarbonImmutable::now();
    }

    /**
     * Manual rating を未評価状態に戻します。
     */
    public function clearManualRating(): void
    {
        $this->manual_rating = null;
        $this->manual_rated_at = null;
    }

    /**
     * Manual rating attribute を検証して設定します。
     */
    public function setManualRatingAttribute(mixed $value): void
    {
        if ($value === null) {
            $this->attributes['manual_rating'] = null;

            return;
        }

        if (! is_int($value)) {
            throw new InvalidArgumentException('Manual rating must be an integer or null.');
        }

        self::validateManualRating($value);

        $this->attributes['manual_rating'] = $value;
    }

    /**
     * Manual rating value が許可範囲内か検証します。
     */
    public static function validateManualRating(int $rating): void
    {
        if ($rating === -1 || ($rating >= 1 && $rating <= 5)) {
            return;
        }

        throw new InvalidArgumentException('Manual rating must be -1, 1, 2, 3, 4, 5, or null.');
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
            'analysis_queued_at' => 'immutable_datetime',
            'analysis_started_at' => 'immutable_datetime',
            'analyzed_at' => 'immutable_datetime',
            'analysis_completed_at' => 'immutable_datetime',
            'analysis_skipped_at' => 'immutable_datetime',
            'analysis_failed_at' => 'immutable_datetime',
            'article_content_queued_at' => 'immutable_datetime',
            'article_content_started_at' => 'immutable_datetime',
            'article_content_fetched_at' => 'immutable_datetime',
            'article_content_skipped_at' => 'immutable_datetime',
            'article_content_failed_at' => 'immutable_datetime',
            'manual_rating' => 'integer',
            'manual_rated_at' => 'immutable_datetime',
        ];
    }
}
