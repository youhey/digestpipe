<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleRatingResource;
use App\Models\DigestItem;
use App\Ratings\DigestItemRatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Downstream からの Article rating 更新 API を扱います。
 */
class ArticleRatingController extends Controller
{
    private DigestItemRatingService $ratings;

    /**
     * Constructor
     *
     * @param DigestItemRatingService $ratings
     */
    public function __construct(DigestItemRatingService $ratings)
    {
        $this->ratings = $ratings;
    }

    /**
     * Article rating を設定または上書きします。
     *
     * @param Request $request
     * @param int $id
     *
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $item = $this->visibleArticle($id);
        $rating = $this->ratingFromRequest($request);

        return $this->ratingResponse($this->ratings->rate($item, $rating), $request);
    }

    /**
     * Article rating を未評価状態に戻します。
     *
     * @param Request $request
     * @param int $id
     *
     * @return JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        return $this->ratingResponse($this->ratings->clear($this->visibleArticle($id)), $request);
    }

    private function visibleArticle(int $id): DigestItem
    {
        $item = DigestItem::query()
            ->where('analysis_status', 'completed')
            ->find($id);

        if (! $item instanceof DigestItem || ! $item->hasCompletedAnalysis()) {
            abort(404);
        }

        return $item;
    }

    /**
     * @throws ValidationException
     */
    private function ratingFromRequest(Request $request): int
    {
        $rating = $request->input('rating');

        if (! is_int($rating) || ! in_array($rating, [-1, 1, 2, 3, 4, 5], true)) {
            throw ValidationException::withMessages([
                'rating' => ['The selected rating is invalid.'],
            ]);
        }

        return $rating;
    }

    private function ratingResponse(DigestItem $item, Request $request): JsonResponse
    {
        return response()->json([
            'article_rating' => (new ArticleRatingResource($item))->resolve($request),
        ]);
    }
}
