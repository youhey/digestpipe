<?php

namespace App\Insights;

use App\Admin\SourceInsightsQuery;
use Carbon\CarbonImmutable;

/**
 * Source Insights page の内容を Markdown として export します。
 *
 * @phpstan-import-type SourceInsightsReport from SourceInsightsQuery
 */
class SourceInsightsExporter
{
    private SourceInsightsQuery $query;

    /**
     * Constructor
     *
     * @param SourceInsightsQuery $query
     */
    public function __construct(SourceInsightsQuery $query)
    {
        $this->query = $query;
    }

    /**
     * Source insights Markdown export を生成します。
     */
    public function export(int $days = 7, string $sort = 'total'): InsightsExportResult
    {
        $now = CarbonImmutable::now();
        $report = $this->query->report($days, $sort);

        return new InsightsExportResult(
            'digestpipe-source-insights-' . $now->format('Ymd-His') . '.md',
            'text/markdown; charset=UTF-8',
            $this->markdown($report, $now),
        );
    }

    /**
     * @param SourceInsightsReport $report
     */
    private function markdown(array $report, CarbonImmutable $generatedAt): string
    {
        return implode("\n", [
            '# digestpipe Source Insights Export',
            '',
            'Generated at: ' . $generatedAt->toJSON(),
            'Period: last ' . $report['period']['days'] . ' days',
            'Purpose: source value and pipeline health comparison',
            '',
            '## Source Comparison',
            '',
            $this->markdownTable([
                'source_key',
                'total',
                'selected_rate',
                'skipped_rate',
                'pending_rate',
                'analysis_completed_rate',
                'failure_rate',
                'average_score',
            ], $report['sources']),
            '',
        ]);
    }

    /**
     * @param list<string> $columns
     * @param list<array<string, mixed>> $rows
     */
    private function markdownTable(array $columns, array $rows): string
    {
        $lines = [
            '| ' . implode(' | ', $columns) . ' |',
            '| ' . implode(' | ', array_map(static fn (): string => '---', $columns)) . ' |',
        ];

        foreach ($rows as $row) {
            $lines[] = '| ' . implode(' | ', array_map(
                fn (string $column): string => $this->escapeMarkdown($this->displayValue($row[$column] ?? null)),
                $columns,
            )) . ' |';
        }

        return implode("\n", $lines);
    }

    private function displayValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'N/A';
        }

        if (is_float($value)) {
            return sprintf('%.2f', $value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : 'N/A';
    }

    private function escapeMarkdown(string $value): string
    {
        return str_replace(["\r", "\n", '|'], [' ', ' ', '\|'], $value);
    }
}
