<?php

namespace App\Processing;

use RuntimeException;

/**
 * AI Processing Service で安全にジョブへ失敗を伝播する例外
 */
class AiProcessingException extends RuntimeException
{
}
