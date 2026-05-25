<?php

namespace App\Console\Commands;

use App\Items\NewsItemProcessingPlan;
use App\Items\NewsItemProcessingPlanner;
use App\Items\NewsItemSelectionResult;
use App\Items\NewsItemSelector;
use App\Jobs\AnalyzeNewsItemJob;
use App\Jobs\FetchNewsItemArticleContentJob;
use App\Models\NewsItem;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * 記事アイテムの状態から次に必要な処理を待ち行列に登録
 */
class EnqueueProcessingCommand extends Command
{
    protected $signature = 'digestpipe:items:enqueue-processing
        {--limit= : Maximum jobs to enqueue}
        {--dry-run : Inspect candidate items without dispatching jobs or changing statuses}
        {--source= : Enqueue only one source key}
        {--stage= : Enqueue only one stage: content or analysis}';

    protected $description = 'State-aware orchestrator for article content and analysis jobs.';

    private readonly NewsItemProcessingPlanner $planner;

    private readonly NewsItemSelector $selector;

    /**
     * Processing orchestration commandを作成します。
     */
    public function __construct(NewsItemProcessingPlanner $planner, NewsItemSelector $selector)
    {
        $this->planner = $planner;
        $this->selector = $selector;

        parent::__construct();
    }

    /**
     * 記事アイテムごとに次に遷移するべき処理を1つだけ `dispatch` する
     *
     * @return int success=0 or failure=1 or invalid=2
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $sourceKey = $this->sourceOption();

        try {
            $limit = $this->limitOption();
            $stage = $this->stageOption();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

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
        ];
        $selectionCounts = [
            'needs_content' => 0,
            'selected' => 0,
            'skipped' => 0,
            'bypassed' => 0,
        ];

        foreach ($items as $item) {
            if ($limit !== null && $dispatchedCount >= $limit) {
                break;
            }

            $selectionResult = $this->selectItem($item, $dryRun);

            if ($selectionResult !== null) {
                $selectionCounts[$selectionResult->status] = ($selectionCounts[$selectionResult->status] ?? 0) + 1;
            } elseif (! $this->selector->enabled()) {
                ++$selectionCounts['bypassed'];
            }

            if ($this->selector->enabled() && $selectionResult !== null && $selectionResult->status === 'skipped') {
                ++$skippedCount;
                $plan = NewsItemProcessingPlan::none($selectionResult->reason);
                $this->logDecision($item, $plan, $dryRun, 'selection_skipped');
                $this->writeDryRunLine($dryRun, $item, $plan);

                continue;
            }

            if ($this->selector->enabled() && ! $this->selectionAllowsPlanning($item, $selectionResult)) {
                ++$skippedCount;
                $plan = NewsItemProcessingPlan::none('selection_' . $this->effectiveSelectionStatus($item, $selectionResult));
                $this->logDecision($item, $plan, $dryRun, 'selection_blocked');
                $this->writeDryRunLine($dryRun, $item, $plan);

                continue;
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
                $this->writeDryRunLine($dryRun, $item, $plan);

                continue;
            }

            $this->logDecision($item, $plan, $dryRun, 'selected');

            if ($dryRun) {
                $this->writeDryRunLine($dryRun, $item, $plan);
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
            'selection_needs_content_count' => $selectionCounts['needs_content'],
            'selection_selected_count' => $selectionCounts['selected'],
            'selection_skipped_count' => $selectionCounts['skipped'],
            'selection_bypassed_count' => $selectionCounts['bypassed'],
        ]);

        $this->info(sprintf(
            'Processing enqueue finished. Candidates: %d, %s: %d, skipped: %d, content: %d, analysis: %d.',
            count($items),
            $dryRun ? 'planned' : 'queued',
            $dispatchedCount,
            $skippedCount,
            $plannedCounts['content'],
            $plannedCounts['analysis'],
        ));

        return self::SUCCESS;
    }

    private function selectItem(NewsItem $item, bool $dryRun): ?NewsItemSelectionResult
    {
        if (! $this->selector->enabled()) {
            return null;
        }

        if ($item->selection_status === 'selected' || $item->selection_status === 'skipped') {
            return null;
        }

        if ($item->selection_status === 'needs_content' && ! $this->hasFinalSelectionInput($item)) {
            return null;
        }

        $result = $this->hasFinalSelectionInput($item)
            ? $this->selector->evaluatePostContent($item)
            : $this->selector->evaluatePreContent($item);

        Log::debug('News item selection evaluated.', [
            'news_item_id' => $item->id,
            'source_key' => $item->source_key,
            'dry_run' => $dryRun,
            'selection_status' => $result->status,
            'selection_score' => $result->score,
            'selection_reason' => $result->reason,
            'matched_good_keyword_count' => count($result->matchedGoodKeywords),
            'matched_bad_keyword_count' => count($result->matchedBadKeywords),
        ]);

        if (! $dryRun) {
            $item->forceFill([
                'selection_status' => $result->status,
                'selection_score' => $result->score,
                'selection_reason' => $result->reason,
                'selection_result' => $result->toArray(),
                'selection_evaluated_at' => CarbonImmutable::now(),
            ])->save();
        }

        return $result;
    }

    private function selectionAllowsPlanning(NewsItem $item, ?NewsItemSelectionResult $selectionResult): bool
    {
        $status = $this->effectiveSelectionStatus($item, $selectionResult);

        if ($status === 'selected') {
            return true;
        }

        if ($status !== 'needs_content') {
            return false;
        }

        return in_array($item->article_content_status, ['pending', 'queued', 'processing'], true);
    }

    private function effectiveSelectionStatus(NewsItem $item, ?NewsItemSelectionResult $selectionResult): string
    {
        if ($selectionResult instanceof NewsItemSelectionResult) {
            return $selectionResult->status;
        }

        return $item->selection_status;
    }

    private function hasFinalSelectionInput(NewsItem $item): bool
    {
        return in_array($item->article_content_status, ['completed', 'skipped'], true);
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
        ])->save();

        match ($plan->jobClass) {
            FetchNewsItemArticleContentJob::class => FetchNewsItemArticleContentJob::dispatch($item->id),
            AnalyzeNewsItemJob::class => AnalyzeNewsItemJob::dispatch($item->id),
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

    private function writeDryRunLine(bool $dryRun, NewsItem $item, NewsItemProcessingPlan $plan): void
    {
        if (! $dryRun) {
            return;
        }

        $this->line(sprintf(
            'DRY RUN: news_item=%d source=%s stage=%s job=%s reason=%s',
            $item->id,
            $item->source_key,
            $plan->stage ?? 'none',
            $this->shortJobName($plan) ?? 'none',
            $plan->reason,
        ));
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
            'selection_status' => $item->selection_status,
            'selection_score' => $item->selection_score,
            'article_content_status' => $item->article_content_status,
            'analysis_status' => $item->analysis_status,
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

        if ($stage === null) {
            return null;
        }

        if (! in_array($stage, ['content', 'analysis'], true)) {
            throw new InvalidArgumentException('The --stage option must be content or analysis.');
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
            return null;
        }

        return $value;
    }
}
