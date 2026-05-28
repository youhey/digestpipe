<?php

namespace App\Console\Commands;

use App\Admin\SourceInsightsQuery;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Feed Source 横断の価値・健全性メトリクスを出力します。
 */
class SourceInsightsCommand extends Command
{
    protected $signature = 'digestpipe:sources:insights
        {--days=7 : Report period in days}
        {--format=table : Output format: table}
        {--sort=total : Sort: total, selected-rate, skipped-rate, pending-rate, analysis-completed-rate, failure-rate, average-score}';

    protected $description = 'Compare source value and pipeline health metrics.';

    private SourceInsightsQuery $query;

    /**
     * Constructor
     *
     * @param SourceInsightsQuery $query
     */
    public function __construct(SourceInsightsQuery $query)
    {
        $this->query = $query;

        parent::__construct();
    }

    /**
     * Source Insights table を出力します。
     *
     * @return int success=0 or invalid=2
     */
    public function handle(): int
    {
        try {
            $days = $this->daysOption();
            $format = $this->formatOption();
            $sort = $this->sortOption();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        if ($format !== 'table') {
            $this->error('The --format option must be [table].');

            return self::INVALID;
        }

        $this->info('Source Insights');
        $this->line('Period: last ' . $days . ' days');
        $this->table(
            ['source_key', 'total', 'selected%', 'skipped%', 'pending%', 'analysis_completed%', 'failure%', 'avg_score'],
            array_map(
                static fn (array $row): array => [
                    $row['source_key'],
                    $row['total'],
                    $row['selected_rate'],
                    $row['skipped_rate'],
                    $row['pending_rate'],
                    $row['analysis_completed_rate'],
                    $row['failure_rate'],
                    $row['average_selection_score'],
                ],
                $this->query->tableRows($days, $sort),
            ),
        );

        return self::SUCCESS;
    }

    private function daysOption(): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);

        if (! is_int($days) || $days < 1) {
            throw new InvalidArgumentException('The --days option must be a positive integer.');
        }

        return $days;
    }

    private function formatOption(): string
    {
        $format = $this->option('format');

        return is_string($format) ? $format : '';
    }

    private function sortOption(): string
    {
        $sort = $this->option('sort');
        $sort = is_string($sort) ? $sort : '';
        $allowed = [
            'total',
            'selected-rate',
            'skipped-rate',
            'pending-rate',
            'analysis-completed-rate',
            'failure-rate',
            'average-score',
        ];

        if (! in_array($sort, $allowed, true)) {
            throw new InvalidArgumentException('The --sort option must be one of: ' . implode(', ', $allowed) . '.');
        }

        return $sort;
    }
}
