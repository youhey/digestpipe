<?php

namespace App\Console\Commands;

use App\Insights\InsightsExportOptions;
use App\Insights\SelectionInsightsExporter;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use Throwable;

/**
 * Selection insights を Markdown として export します。
 */
class InsightsExportCommand extends Command
{
    protected $signature = 'digestpipe:insights:export
        {--days=7 : Export period in days}
        {--source= : Filter by source_key}
        {--sample-limit=20 : Limit recent selected/skipped item examples}
        {--keyword-limit=20 : Limit top matched keyword rows}
        {--format=markdown : Export format}
        {--output= : Write output to a file path instead of stdout}';

    protected $description = 'Export selection insights as compact Markdown.';

    private SelectionInsightsExporter $exporter;

    private Filesystem $files;

    /**
     * Constructor
     *
     * @param SelectionInsightsExporter $exporter
     * @param Filesystem $files
     */
    public function __construct(SelectionInsightsExporter $exporter, Filesystem $files)
    {
        $this->exporter = $exporter;
        $this->files = $files;

        parent::__construct();
    }

    /**
     * Selection insights Markdown を標準出力または file に出力します。
     *
     * @return int success=0 or invalid=2 or failure=1
     */
    public function handle(): int
    {
        try {
            $result = $this->exporter->export($this->optionsFromInput());
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $output = $this->outputOption();

        if ($output === null) {
            $this->line($result->content);

            return self::SUCCESS;
        }

        try {
            $written = $this->files->put($output, $result->content);
        } catch (Throwable) {
            $written = false;
        }

        if ($written === false) {
            $this->error("Unable to write insights export to [{$output}].");

            return self::FAILURE;
        }

        $this->info("Insights export written to [{$output}].");

        return self::SUCCESS;
    }

    private function optionsFromInput(): InsightsExportOptions
    {
        return InsightsExportOptions::make(
            days: $this->positiveIntOption('days'),
            source: $this->stringOption('source'),
            sampleLimit: $this->positiveIntOption('sample-limit'),
            keywordLimit: $this->positiveIntOption('keyword-limit'),
            format: $this->stringOption('format') ?? 'markdown',
        );
    }

    private function positiveIntOption(string $name): int
    {
        $value = filter_var($this->option($name), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (! is_int($value)) {
            throw new InvalidArgumentException("The --{$name} option must be a positive integer.");
        }

        return $value;
    }

    private function outputOption(): ?string
    {
        return $this->stringOption('output');
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
