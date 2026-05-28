<?php

namespace App\Insights;

/**
 * 生成済み insights export の内容と download metadata を保持します。
 */
class InsightsExportResult
{
    /** @var string download filename */
    public string $filename;

    /** @var string response MIME type */
    public string $mimeType;

    /** @var string export content */
    public string $content;

    /**
     * Constructor
     *
     * @param string $filename
     * @param string $mimeType
     * @param string $content
     */
    public function __construct(string $filename, string $mimeType, string $content)
    {
        $this->filename = $filename;
        $this->mimeType = $mimeType;
        $this->content = $content;
    }
}
