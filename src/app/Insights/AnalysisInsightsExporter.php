<?php

namespace App\Insights;

use App\Admin\AnalysisInsightsQuery;
use Carbon\CarbonImmutable;

/**
 * Analysis Insights page の内容を Markdown として export します。
 *
 * @phpstan-import-type AnalysisInsightsReport from AnalysisInsightsQuery
 */
class AnalysisInsightsExporter
{
    private AnalysisInsightsQuery $query;

    /**
     * Constructor
     *
     * @param AnalysisInsightsQuery $query
     */
    public function __construct(AnalysisInsightsQuery $query)
    {
        $this->query = $query;
    }

    /**
     * Analysis insights Markdown export を生成します。
     */
    public function export(int $days = 30): InsightsExportResult
    {
        $now = CarbonImmutable::now();
        $report = $this->query->report(days: $days, sampleLimit: 100, lowConfidenceLimit: 100);

        return new InsightsExportResult(
            'digestpipe-analysis-insights-' . $now->format('Ymd-His') . '.md',
            'text/markdown; charset=UTF-8',
            $this->markdown($report, $now),
        );
    }

    /**
     * @param AnalysisInsightsReport $report
     */
    private function markdown(array $report, CarbonImmutable $generatedAt): string
    {
        return implode("\n", [
            '# digestpipe Analysis Insights Export',
            '',
            'Generated at: ' . $generatedAt->toJSON(),
            'Period: last ' . $report['period']['days'] . ' days',
            'Purpose: AI analysis output quality inspection',
            '',
            '## Content Type Breakdown',
            '',
            $this->markdownTable(['content_type', 'count'], $report['content_types']),
            '',
            '## Content Type by Source',
            '',
            $this->markdownTable(['source_key', 'content_type', 'count'], $report['content_types_by_source']),
            '',
            '## Confidence Distribution',
            '',
            $this->markdownTable(['label', 'count'], $report['confidence_distribution']),
            '',
            '## Importance Distribution',
            '',
            $this->markdownTable(['label', 'count'], $report['importance_distribution']),
            '',
            '## Recent Analysis Samples',
            '',
            $this->markdownTable(['id', 'source_key', 'content_type', 'confidence', 'importance', 'title'], $report['recent_samples']),
            '',
            '## Low Confidence Items',
            '',
            $this->markdownTable(['id', 'source_key', 'confidence', 'content_type', 'title', 'limitations'], $report['low_confidence_items']),
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
