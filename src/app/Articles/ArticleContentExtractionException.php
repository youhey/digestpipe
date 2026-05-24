<?php

namespace App\Articles;

use RuntimeException;

/**
 * HTML から十分な記事本文を抽出できなかった例外
 */
class ArticleContentExtractionException extends RuntimeException
{
}
