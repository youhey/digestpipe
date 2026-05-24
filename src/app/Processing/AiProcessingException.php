<?php

namespace App\Processing;

use RuntimeException;

/**
 * AI processing serviceで安全にjobへ伝播する例外です。
 */
class AiProcessingException extends RuntimeException
{
}
