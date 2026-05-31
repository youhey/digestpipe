<?php

namespace App\Ratings;

use App\Models\DigestItem;

/**
 * Digest Item の manual rating を更新します。
 */
class DigestItemRatingService
{
    /**
     * Digest Item に rating を設定します。
     *
     * @param DigestItem $item
     * @param int $rating
     *
     * @return DigestItem
     */
    public function rate(DigestItem $item, int $rating): DigestItem
    {
        $item->setManualRating($rating);
        $item->save();

        return $item->refresh();
    }

    /**
     * Digest Item の rating を消去します。
     *
     * @param DigestItem $item
     *
     * @return DigestItem
     */
    public function clear(DigestItem $item): DigestItem
    {
        $item->clearManualRating();
        $item->save();

        return $item->refresh();
    }
}
