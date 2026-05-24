<?php

namespace App\Articles;

use RuntimeException;

/**
 * HTMLから十分な記事本文を抽出できない場合の例外です。
 */
class ArticleContentExtractionException extends RuntimeException
{
}
