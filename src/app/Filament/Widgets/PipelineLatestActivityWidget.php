<?php

namespace App\Filament\Widgets;

use App\Admin\PipelineHealthStatsQuery;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Pipeline の最新 activity timestamp を表示する dashboard widget
 */
class PipelineLatestActivityWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Latest pipeline activity';

    protected ?string $description = 'Last 7 days';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $latest = app(PipelineHealthStatsQuery::class)->report()['latest'];

        return [
            Stat::make('Latest Digest Item created', $this->timestamp($latest['latest_digest_item_created_at']))
                ->icon(Heroicon::PlusCircle),
            Stat::make('Latest feed fetched', $this->timestamp($latest['latest_feed_fetched_at']))
                ->icon(Heroicon::Rss),
            Stat::make('Latest content fetched', $this->timestamp($latest['latest_article_content_fetched_at']))
                ->icon(Heroicon::DocumentText),
            Stat::make('Latest analysis completed', $this->timestamp($latest['latest_analysis_completed_at']))
                ->icon(Heroicon::Sparkles),
        ];
    }

    private function timestamp(?string $value): HtmlString
    {
        return new HtmlString('<span style="display: inline-block; white-space: nowrap; font-size: 1rem; line-height: 1.5rem;">' . e($this->formattedTimestamp($value)) . '</span>');
    }

    private function formattedTimestamp(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return 'N/A';
        }

        try {
            $timezone = config('app.timezone', 'UTC');

            if (! is_string($timezone) || trim($timezone) === '') {
                $timezone = 'UTC';
            }

            return CarbonImmutable::parse($value)
                ->timezone($timezone)
                ->format('Y-m-d H:i:s T');
        } catch (Throwable) {
            return $value;
        }
    }
}
