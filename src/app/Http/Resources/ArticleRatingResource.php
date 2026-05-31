<?php

namespace App\Http\Resources;

use App\Models\DigestItem;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Article rating API の外部向け表現を生成します。
 *
 * @mixin DigestItem
 */
class ArticleRatingResource extends JsonResource
{
    /**
     * Article rating response の配列を返します。
     *
     * @param Request $request
     *
     * @return array{article_id: int, rating: int|null, rated_at: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'article_id' => $this->id,
            'rating' => $this->manual_rating,
            'rated_at' => $this->timestamp($this->manual_rated_at),
        ];
    }

    private function timestamp(mixed $value): ?string
    {
        if (! $value instanceof CarbonInterface) {
            return null;
        }

        return $value->toJSON();
    }
}
