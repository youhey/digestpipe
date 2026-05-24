<?php

namespace App\Console\Commands;

use App\Items\NewsItemProcessingPlan;
use App\Items\NewsItemProcessingPlanner;
use App\Jobs\AnalyzeNewsItemJob;
use App\Jobs\FetchNewsItemArticleContentJob;
use App\Jobs\SummarizeNewsItemJob;
use App\Jobs\TranslateNewsItemJob;
use App\Models\NewsItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * News itemの状態を見て、次に必要なprocessing jobをqueueへ投入するbatch commandです。
 */
class EnqueueProcessingCommand extends Command
{
    protected $signature = 'digestpipe:items:enqueue-processing
        {--limit= : Maximum jobs to enqueue}
        {--dry-run : Inspect candidate items without dispatching jobs or changing statuses}
        {--source= : Enqueue only one source key}
        {--stage= : Enqueue only one stage: content, analysis, translation, or summary}
        {--only= : Backward-compatible alias for --stage=translation or --stage=summary}';

    protected $description = 'State-aware orchestrator for article content and analysis jobs.';

    private readonly NewsItemProcessingPlanner $planner;

    /**
     * Processing orchestration commandを作成します。
     */
    public function __construct(NewsItemProcessingPlanner $planner)
    {
        $this->planner = $planner;

        parent::__construct();
    }

    /**
     * News itemごとに次の有効なprocessing jobを1つだけdispatchします。
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->limitOption();
        $sourceKey = $this->sourceOption();
        $stage = $this->stageOption();

        Log::info('News item processing enqueue command started.', [
            'dry_run' => $dryRun,
            'source_filter' => $sourceKey,
            'stage_filter' => $stage,
            'limit' => $limit,
        ]);

        $items = $this->candidateItems($sourceKey);
        $dispatchedCount = 0;
        $skippedCount = 0;
        $plannedCounts = [
            'content' => 0,
            'analysis' => 0,
            'translation' => 0,
            'summary' => 0,
        ];

        foreach ($items as $item) {
            if ($limit !== null && $dispatchedCount >= $limit) {
                break;
            }

            $plan = $this->planner->plan($item, $stage);

            if ($stage !== null && $plan->stage !== $stage) {
                ++$skippedCount;
                $this->logDecision($item, $plan, $dryRun, 'stage_filtered');

                continue;
            }

            if (! $plan->shouldDispatch()) {
                ++$skippedCount;
                $this->logDecision($item, $plan, $dryRun, 'skipped');

                continue;
            }

            $this->logDecision($item, $plan, $dryRun, 'selected');

            if ($dryRun) {
                $this->line(sprintf(
                    'DRY RUN: news_item=%d source=%s stage=%s job=%s reason=%s',
                    $item->id,
                    $item->source_key,
                    $plan->stage,
                    $this->shortJobName($plan),
                    $plan->reason,
                ));
            } else {
                $this->markQueuedAndDispatch($item, $plan);
            }

            ++$dispatchedCount;

            if ($plan->stage !== null) {
                ++$plannedCounts[$plan->stage];
            }
        }

        Log::info('News item processing enqueue command finished.', [
            'dry_run' => $dryRun,
            'source_filter' => $sourceKey,
            'stage_filter' => $stage,
            'limit' => $limit,
            'candidate_item_count' => count($items),
            'dispatched_job_count' => $dryRun ? 0 : $dispatchedCount,
            'planned_job_count' => $dryRun ? $dispatchedCount : 0,
            'skipped_item_count' => $skippedCount,
            'content_job_count' => $plannedCounts['content'],
            'analysis_job_count' => $plannedCounts['analysis'],
            'translation_job_count' => $plannedCounts['translation'],
            'summary_job_count' => $plannedCounts['summary'],
        ]);

        $this->info(sprintf(
            'Processing enqueue finished. Candidates: %d, %s: %d, skipped: %d, content: %d, analysis: %d, translation: %d, summary: %d.',
            count($items),
            $dryRun ? 'planned' : 'queued',
            $dispatchedCount,
            $skippedCount,
            $plannedCounts['content'],
            $plannedCounts['analysis'],
            $plannedCounts['translation'],
            $plannedCounts['summary'],
        ));

        return self::SUCCESS;
    }

    /**
     * @return list<NewsItem>
     */
    private function candidateItems(?string $sourceKey): array
    {
        $query = NewsItem::query();

        if ($sourceKey !== null) {
            $query->where('source_key', $sourceKey);
        }

        /** @var list<NewsItem> $items */
        $items = $query->get()->all();

        usort($items, static fn (NewsItem $left, NewsItem $right): int => $left->id <=> $right->id);

        return $items;
    }

    private function markQueuedAndDispatch(NewsItem $item, NewsItemProcessingPlan $plan): void
    {
        if ($plan->statusField === null || $plan->jobClass === null) {
            return;
        }

        $item->forceFill([
            $plan->statusField => 'queued',
            'processing_error' => null,
        ])->save();

        match ($plan->jobClass) {
            FetchNewsItemArticleContentJob::class => FetchNewsItemArticleContentJob::dispatch($item->id),
            AnalyzeNewsItemJob::class => AnalyzeNewsItemJob::dispatch($item->id),
            TranslateNewsItemJob::class => TranslateNewsItemJob::dispatch($item->id),
            SummarizeNewsItemJob::class => SummarizeNewsItemJob::dispatch($item->id),
            default => throw new InvalidArgumentException("Unsupported processing job [{$plan->jobClass}]."),
        };

        Log::info('News item processing job queued.', [
            'news_item_id' => $item->id,
            'source_key' => $item->source_key,
            'stage' => $plan->stage,
            'job' => $this->shortJobName($plan),
            'reason' => $plan->reason,
            'status_field' => $plan->statusField,
        ]);
    }

    private function logDecision(NewsItem $item, NewsItemProcessingPlan $plan, bool $dryRun, string $decision): void
    {
        Log::info('News item processing decision.', [
            'news_item_id' => $item->id,
            'source_key' => $item->source_key,
            'decision' => $decision,
            'dry_run' => $dryRun,
            'stage' => $plan->stage,
            'job' => $this->shortJobName($plan),
            'reason' => $plan->reason,
            'article_content_status' => $item->article_content_status,
            'analysis_status' => $item->analysis_status,
            'translation_status' => $item->translation_status,
            'summary_status' => $item->summary_status,
        ]);
    }

    private function shortJobName(NewsItemProcessingPlan $plan): ?string
    {
        if ($plan->jobClass === null) {
            return null;
        }

        $parts = explode('\\', $plan->jobClass);

        return end($parts);
    }

    private function sourceOption(): ?string
    {
        $value = $this->option('source');

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function stageOption(): ?string
    {
        $stage = $this->stringOption('stage');
        $only = $this->stringOption('only');

        if ($stage === null && $only !== null) {
            $stage = $only;
        }

        if ($stage === null) {
            return null;
        }

        if (! in_array($stage, ['content', 'analysis', 'translation', 'summary'], true)) {
            throw new InvalidArgumentException('The --stage option must be content, analysis, translation, or summary.');
        }

        return $stage;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function limitOption(): ?int
    {
        $value = $this->option('limit');

        if ($value === null || $value === '') {
            return $this->configuredBatchLimit();
        }

        $limit = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (! is_int($limit)) {
            throw new InvalidArgumentException('The --limit option must be a positive integer.');
        }

        return $limit;
    }

    private function configuredBatchLimit(): ?int
    {
        $value = config('digestpipe.analysis.batch_limit');

        if (! is_int($value) || $value < 1) {
            $value = config('digestpipe.ai.batch_limit');
        }

        if (! is_int($value) || $value < 1) {
            return null;
        }

        return $value;
    }
}
