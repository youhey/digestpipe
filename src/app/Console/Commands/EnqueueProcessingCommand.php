<?php

namespace App\Console\Commands;

use App\Feeds\FeedSource;
use App\Feeds\FeedSourceRepository;
use App\Items\DigestItemProcessingPlan;
use App\Items\DigestItemProcessingPlanner;
use App\Items\DigestItemSelectionResult;
use App\Items\DigestItemSelector;
use App\Items\SelectionEvaluationRecorder;
use App\Jobs\AnalyzeDigestItemJob;
use App\Jobs\FetchDigestItemArticleContentJob;
use App\Models\DigestItem;
use App\Models\DigestpipeCommandRun;
use App\Support\DigestpipeCommandRunRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Digest Item の状態から次に必要な処理を待ち行列に登録
 */
class EnqueueProcessingCommand extends Command
{
    protected $signature = 'digestpipe:items:enqueue-processing
        {--limit= : Maximum candidates to inspect}
        {--per-source-limit= : Maximum candidates to inspect per source when --source is not specified}
        {--dry-run : Inspect candidate items without dispatching jobs or changing statuses}
        {--source= : Enqueue only one source key}
        {--stage= : Enqueue only one stage: content or analysis}';

    protected $description = 'State-aware orchestrator for article content and analysis jobs.';

    private readonly DigestItemProcessingPlanner $planner;

    private readonly DigestItemSelector $selector;

    private readonly SelectionEvaluationRecorder $selectionEvaluations;

    private readonly DigestpipeCommandRunRecorder $commandRuns;

    private readonly FeedSourceRepository $sources;

    /**
     * Processing orchestration commandを作成します。
     */
    public function __construct(
        DigestItemProcessingPlanner $planner,
        DigestItemSelector $selector,
        SelectionEvaluationRecorder $selectionEvaluations,
        DigestpipeCommandRunRecorder $commandRuns,
        FeedSourceRepository $sources
    ) {
        $this->planner = $planner;
        $this->selector = $selector;
        $this->selectionEvaluations = $selectionEvaluations;
        $this->commandRuns = $commandRuns;
        $this->sources = $sources;

        parent::__construct();
    }

    /**
     * Digest Item ごとに次に遷移するべき処理を1つだけ `dispatch` する
     *
     * @return int success=0 or failure=1 or invalid=2
     */
    public function handle(): int
    {
        $run = $this->commandRuns->start('digestpipe:items:enqueue-processing', $this->commandArguments());

        try {
            return $this->handleWithRun($run);
        } catch (Throwable $exception) {
            $this->commandRuns->fail($run, $exception);

            throw $exception;
        }
    }

    private function handleWithRun(DigestpipeCommandRun $run): int
    {
        $dryRun = $this->option('dry-run');
        $sourceKey = $this->sourceOption();

        try {
            $limit = $this->limitOption();
            $perSourceLimit = $this->perSourceLimitOption($sourceKey);
            $stage = $this->stageOption();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            $this->commandRuns->complete($run, [
                'exit_code' => self::INVALID,
                'dry_run' => $dryRun,
                'source' => $sourceKey,
                'error' => $exception->getMessage(),
            ]);

            return self::INVALID;
        }

        Log::info('Digest item processing enqueue command started.', [
            'dry_run' => $dryRun,
            'source_filter' => $sourceKey,
            'stage_filter' => $stage,
            'limit' => $limit,
            'per_source_limit' => $perSourceLimit,
        ]);

        $items = $this->candidateItems($sourceKey, $limit, $perSourceLimit);
        $sourceSummary = $this->initialSourceSummary($items);
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
                $plan = DigestItemProcessingPlan::none($selectionResult->reason);
                $this->recordSourceDecision($sourceSummary, $item, $plan, false);
                $this->logDecision($item, $plan, $dryRun, 'selection_skipped');
                $this->writeDryRunLine($dryRun, $item, $plan);

                continue;
            }

            if ($this->selector->enabled() && ! $this->selectionAllowsPlanning($item, $selectionResult)) {
                ++$skippedCount;
                $plan = DigestItemProcessingPlan::none('selection_' . $this->effectiveSelectionStatus($item, $selectionResult));
                $this->recordSourceDecision($sourceSummary, $item, $plan, false);
                $this->logDecision($item, $plan, $dryRun, 'selection_blocked');
                $this->writeDryRunLine($dryRun, $item, $plan);

                continue;
            }

            $plan = $this->planner->plan($item, $stage);

            if ($stage !== null && $plan->stage !== $stage) {
                ++$skippedCount;
                $this->recordSourceDecision($sourceSummary, $item, $plan, false);
                $this->logDecision($item, $plan, $dryRun, 'stage_filtered');

                continue;
            }

            if (! $plan->shouldDispatch()) {
                ++$skippedCount;
                $this->recordSourceDecision($sourceSummary, $item, $plan, false);
                $this->logDecision($item, $plan, $dryRun, 'skipped');
                $this->writeDryRunLine($dryRun, $item, $plan);

                continue;
            }

            $this->recordSourceDecision($sourceSummary, $item, $plan, true);
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

        Log::info('Digest item processing enqueue command finished.', [
            'dry_run' => $dryRun,
            'source_filter' => $sourceKey,
            'stage_filter' => $stage,
            'limit' => $limit,
            'per_source_limit' => $perSourceLimit,
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
            'source_summary' => array_values($sourceSummary),
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
        $this->writeSourceSummary($sourceSummary);
        $this->commandRuns->complete($run, [
            'exit_code' => self::SUCCESS,
            'dry_run' => $dryRun,
            'limit' => $limit,
            'per_source_limit' => $perSourceLimit,
            'source' => $sourceKey,
            'stage' => $stage,
            'checked' => count($items),
            'queued' => $dryRun ? 0 : $dispatchedCount,
            'planned' => $dryRun ? $dispatchedCount : 0,
            'skipped' => $skippedCount,
            'content_fetch_dispatched' => $plannedCounts['content'],
            'analysis_dispatched' => $plannedCounts['analysis'],
            'selection_needs_content' => $selectionCounts['needs_content'],
            'selection_selected' => $selectionCounts['selected'],
            'selection_skipped' => $selectionCounts['skipped'],
            'selection_bypassed' => $selectionCounts['bypassed'],
            'sources' => array_values($sourceSummary),
        ]);

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function commandArguments(): array
    {
        return [
            'limit' => $this->option('limit'),
            'per_source_limit' => $this->option('per-source-limit'),
            'dry_run' => $this->option('dry-run'),
            'source' => $this->option('source'),
            'stage' => $this->option('stage'),
        ];
    }

    private function selectItem(DigestItem $item, bool $dryRun): ?DigestItemSelectionResult
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

        $phase = $this->hasFinalSelectionInput($item) ? 'post_content' : 'pre_content';
        $result = $phase === 'post_content'
            ? $this->selector->evaluatePostContent($item)
            : $this->selector->evaluatePreContent($item);
        $evaluatedAt = CarbonImmutable::now();

        Log::debug('Digest item selection evaluated.', [
            'digest_item_id' => $item->id,
            'source_key' => $item->source_key,
            'selection_phase' => $phase,
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
                'selection_evaluated_at' => $evaluatedAt,
            ])->save();

            $this->selectionEvaluations->record($item, $result, $phase, $evaluatedAt);
        }

        return $result;
    }

    private function selectionAllowsPlanning(DigestItem $item, ?DigestItemSelectionResult $selectionResult): bool
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

    private function effectiveSelectionStatus(DigestItem $item, ?DigestItemSelectionResult $selectionResult): string
    {
        if ($selectionResult instanceof DigestItemSelectionResult) {
            return $selectionResult->status;
        }

        return $item->selection_status;
    }

    private function hasFinalSelectionInput(DigestItem $item): bool
    {
        return in_array($item->article_content_status, ['completed', 'skipped'], true);
    }

    /**
     * @return list<DigestItem>
     */
    private function candidateItems(?string $sourceKey, ?int $limit, ?int $perSourceLimit): array
    {
        if ($sourceKey !== null) {
            return $this->candidateItemsForSource($sourceKey, $limit);
        }

        $items = [];

        foreach ($this->eligibleSourceKeys() as $eligibleSourceKey) {
            $items = array_merge($items, $this->candidateItemsForSource($eligibleSourceKey, $perSourceLimit));
        }

        usort($items, static fn (DigestItem $left, DigestItem $right): int => $left->id <=> $right->id);

        if ($limit !== null) {
            return array_slice($items, 0, $limit);
        }

        return $items;
    }

    /**
     * @return list<DigestItem>
     */
    private function candidateItemsForSource(string $sourceKey, ?int $limit): array
    {
        $idQuery = DB::table('digest_items')
            ->select('id')
            ->where('source_key', $sourceKey)
            ->where('selection_status', '!=', 'skipped')
            ->where(static function (QueryBuilder $query): void {
                $query
                    ->where('article_content_status', 'pending')
                    ->orWhere(static function (QueryBuilder $query): void {
                        $query
                            ->whereIn('article_content_status', ['completed', 'skipped'])
                            ->where('analysis_status', 'pending');
                    });
            })
            ->orderBy('id');

        if ($limit !== null) {
            $idQuery->limit($limit);
        }

        $ids = [];

        foreach ($idQuery->pluck('id')->all() as $id) {
            if (is_int($id)) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            return [];
        }

        $itemsById = DigestItem::query()
            ->whereKey($ids)
            ->get()
            ->keyBy('id');

        $items = [];

        foreach ($ids as $id) {
            $item = $itemsById->get($id);

            if ($item instanceof DigestItem) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private function eligibleSourceKeys(): array
    {
        return array_map(
            static fn (FeedSource $source): string => $source->key,
            $this->sources->analysisEnabledSources(),
        );
    }

    private function markQueuedAndDispatch(DigestItem $item, DigestItemProcessingPlan $plan): void
    {
        if ($plan->statusField === null || $plan->jobClass === null) {
            return;
        }

        $queuedAtField = match ($plan->statusField) {
            'article_content_status' => 'article_content_queued_at',
            'analysis_status' => 'analysis_queued_at',
            default => null,
        };
        $attributes = [
            $plan->statusField => 'queued',
        ];

        if ($queuedAtField !== null) {
            $attributes[$queuedAtField] = CarbonImmutable::now();
        }

        $item->forceFill([
            ...$attributes,
        ])->save();

        match ($plan->jobClass) {
            FetchDigestItemArticleContentJob::class => FetchDigestItemArticleContentJob::dispatch($item->id),
            AnalyzeDigestItemJob::class => AnalyzeDigestItemJob::dispatch($item->id),
            default => throw new InvalidArgumentException("Unsupported processing job [{$plan->jobClass}]."),
        };

        Log::info('Digest item processing job queued.', [
            'digest_item_id' => $item->id,
            'source_key' => $item->source_key,
            'stage' => $plan->stage,
            'job' => $this->shortJobName($plan),
            'reason' => $plan->reason,
            'status_field' => $plan->statusField,
        ]);
    }

    private function writeDryRunLine(bool $dryRun, DigestItem $item, DigestItemProcessingPlan $plan): void
    {
        if (! $dryRun) {
            return;
        }

        $this->line(sprintf(
            'DRY RUN: digest_item=%d source=%s stage=%s job=%s reason=%s',
            $item->id,
            $item->source_key,
            $plan->stage ?? 'none',
            $this->shortJobName($plan) ?? 'none',
            $plan->reason,
        ));
    }

    private function logDecision(DigestItem $item, DigestItemProcessingPlan $plan, bool $dryRun, string $decision): void
    {
        Log::info('Digest item processing decision.', [
            'digest_item_id' => $item->id,
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

    private function shortJobName(DigestItemProcessingPlan $plan): ?string
    {
        if ($plan->jobClass === null) {
            return null;
        }

        $parts = explode('\\', $plan->jobClass);

        return end($parts);
    }

    /**
     * @param list<DigestItem> $items
     *
     * @return array<string, array{source_key: string, candidates: int, planned: int, skipped: int, content: int, analysis: int}>
     */
    private function initialSourceSummary(array $items): array
    {
        $summary = [];

        foreach ($items as $item) {
            $sourceKey = $item->source_key;

            if (! isset($summary[$sourceKey])) {
                $summary[$sourceKey] = [
                    'source_key' => $sourceKey,
                    'candidates' => 0,
                    'planned' => 0,
                    'skipped' => 0,
                    'content' => 0,
                    'analysis' => 0,
                ];
            }

            ++$summary[$sourceKey]['candidates'];
        }

        ksort($summary);

        return $summary;
    }

    /**
     * @param array<string, array{source_key: string, candidates: int, planned: int, skipped: int, content: int, analysis: int}> $summary
     */
    private function recordSourceDecision(array &$summary, DigestItem $item, DigestItemProcessingPlan $plan, bool $planned): void
    {
        $sourceKey = $item->source_key;

        if (! isset($summary[$sourceKey])) {
            $summary[$sourceKey] = [
                'source_key' => $sourceKey,
                'candidates' => 0,
                'planned' => 0,
                'skipped' => 0,
                'content' => 0,
                'analysis' => 0,
            ];
        }

        if (! $planned) {
            ++$summary[$sourceKey]['skipped'];

            return;
        }

        ++$summary[$sourceKey]['planned'];

        if ($plan->stage === 'content') {
            ++$summary[$sourceKey]['content'];
        }

        if ($plan->stage === 'analysis') {
            ++$summary[$sourceKey]['analysis'];
        }
    }

    /**
     * @param array<string, array{source_key: string, candidates: int, planned: int, skipped: int, content: int, analysis: int}> $sourceSummary
     */
    private function writeSourceSummary(array $sourceSummary): void
    {
        if ($sourceSummary === []) {
            return;
        }

        $this->line('Source summary:');

        foreach ($sourceSummary as $summary) {
            $this->line(sprintf(
                'source=%s candidates=%d planned=%d skipped=%d content=%d analysis=%d',
                $summary['source_key'],
                $summary['candidates'],
                $summary['planned'],
                $summary['skipped'],
                $summary['content'],
                $summary['analysis'],
            ));
        }
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

    private function perSourceLimitOption(?string $sourceKey): ?int
    {
        if ($sourceKey !== null) {
            return null;
        }

        $value = $this->option('per-source-limit');

        if ($value === null || $value === '') {
            return 10;
        }

        $limit = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (! is_int($limit)) {
            throw new InvalidArgumentException('The --per-source-limit option must be a positive integer.');
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
