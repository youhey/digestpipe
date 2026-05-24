<?php

namespace App\Processing;

use App\Models\NewsItem;

/**
 * AI処理へ渡す本文入力をnews itemから選択します。
 */
class NewsItemTextSelector
{
    /**
     * 記事本文、意味のあるexcerpt、titleの順でAI入力用textを返します。
     */
    public function bodyText(NewsItem $item): ?string
    {
        $text = $this->cleanText($item->article_content_text);

        if ($text !== null) {
            return $this->limitText($text);
        }

        $text = $this->cleanText($item->excerpt);

        if ($text !== null) {
            return $this->limitText($text);
        }

        return $this->limitText($item->title);
    }

    private function cleanText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5));
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", is_string($text) ? $text : '');
        $text = is_string($text) ? trim($text) : '';

        return $text === '' ? null : $text;
    }

    private function limitText(string $value): string
    {
        $maxChars = config('digestpipe.content.max_chars');
        $limit = is_int($maxChars) && $maxChars > 0 ? $maxChars : 8000;

        return substr($value, 0, $limit);
    }
}
