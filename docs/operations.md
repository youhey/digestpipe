# Operations

## Laravel Cloud Wake Polling

Laravel Cloud Starter environments can hibernate when they are idle. While an
environment is hibernating, scheduled tasks and App Cluster background queue
workers may not run until the application is woken by traffic.

digestpipe uses Laravel's standard health endpoint:

```txt
GET /up
```

The endpoint is unauthenticated, lightweight, and intended for health checks and
manual wake polling. It should not expose private application data.

For manual operation windows, run the local polling helper from the repository
root:

```bash
./scripts/digestpipe-poll.sh example.laravel.cloud
```

With an explicit interval:

```bash
./scripts/digestpipe-poll.sh example.laravel.cloud 300
```

For local development, `localhost` and `localhost:<port>` use `http://`:

```bash
./scripts/digestpipe-poll.sh localhost:8080 60
```

Other domains use `https://`. The script calls `/up`, prints a UTC timestamp
for each request, and exits visibly when `curl` fails. It does not require an
API token, does not call private API routes, and does not enqueue jobs.

This helper is optional. Do not treat it as production infrastructure, a daemon,
or a replacement for Laravel Cloud Scheduled Tasks and background processes.

## Scheduled Task Mutex

Laravel Scheduler commands use `withoutOverlapping()` locks to prevent duplicate
scheduled executions.

Current scheduled commands:

```txt
digestpipe:feeds:fetch
digestpipe:items:enqueue-processing --limit=100 --per-source-limit=10
```

Current scheduler mutex expiration values:

```txt
digestpipe:feeds:fetch: 15 minutes
digestpipe:items:enqueue-processing: 10 minutes
```

If scheduled tasks appear to stop running, a stale scheduler mutex may be one
possible cause. For recovery or debugging, clear Laravel's scheduler mutex cache:

```bash
php artisan schedule:clear-cache
```

This command is for recovery and investigation. Do not run it periodically as
part of normal operation.

## Feed Source Master Data

Feed Sources are DB-backed master data in the `feed_sources` table. The
authoritative runtime source is the database, not `config/digestpipe.php`.

Initial Feed Sources are inserted by `FeedSourceSeeder`:

```bash
php artisan db:seed --class=FeedSourceSeeder
```

The seeder uses `key` as the stable identifier and does not overwrite existing
records. This allows later edits made through the Filament admin panel to remain
in place during normal seed runs.

## Selection Keyword Master Data

Selection Keywords are DB-backed master data in the `selection_keywords` table.
The authoritative runtime source is the database, not `selection.positive_keywords`
or `selection.negative_keywords` in `config/digestpipe.php`.

Initial Selection Keywords are inserted by `SelectionKeywordSeeder`:

```bash
php artisan db:seed --class=SelectionKeywordSeeder
```

The seeder stores positive and negative keywords in one table using the `type`
column. It uses `type` + `keyword` as the stable identifier and does not
overwrite existing records.

## Queued Job Compatibility

Internal Digest Item jobs currently use these class names:

```txt
App\Jobs\FetchDigestItemArticleContentJob
App\Jobs\AnalyzeDigestItemJob
```

Temporary compatibility wrappers remain for older queued payloads that reference
the previous `NewsItem` job class names. These wrappers delegate to the current
Digest Item jobs.

Before removing the wrappers in a later cleanup, drain or discard any queued
payloads that still reference the old job class names.

## Selection Report

Use the selection report command to inspect keyword-based item selection from
the Laravel application runtime:

```bash
php artisan digestpipe:selection:report
```

This is useful on Laravel Cloud, where the production MySQL database is private
and not normally accessed directly from local development tools. The command is
read-only. It does not change selection status, enqueue jobs, fetch articles, or
call OpenAI.

Examples:

```bash
php artisan digestpipe:selection:report --hours=48
php artisan digestpipe:selection:report --source=hacker_news
php artisan digestpipe:selection:report --format=json
```

The report includes summary counts, source breakdowns, matched keyword counts,
and recent selected/skipped item examples. The time window uses
`selection_evaluated_at` when available, then falls back to the required
`fetched_at` timestamp.

## Insights Export

Use the insights export command to prepare a compact Markdown report for
ChatGPT-assisted selection analysis:

```bash
php artisan digestpipe:insights:export
```

Options:

```txt
--days=7
--source=
--sample-limit=20
--keyword-limit=20
--format=markdown
--output=
```

Examples:

```bash
php artisan digestpipe:insights:export --days=7
php artisan digestpipe:insights:export --source=hacker_news --sample-limit=30
php artisan digestpipe:insights:export --days=7 --output=/tmp/digestpipe-insights.md
```

The export includes metadata, a suggested ChatGPT analysis prompt, summary
counts, source breakdown, top positive and negative keyword counts, and recent
selected/skipped examples. Markdown is the only format in the first version.

The export uses `selection_evaluated_at` as the timestamp basis, then falls back
to `updated_at`, then `created_at`. It intentionally excludes raw article
content, raw HTML, full `analysis_json`, full per-item `selection_result` JSON,
and secrets.

The same export is available from the Filament dashboard header as `Export
Insights`. Future presets may add analysis or operations exports, but this
version is selection-focused.

## Selection Evaluation History

Selection evaluation history is stored in the `selection_evaluations` table.
Each row records one selection evaluation for a Digest Item.

Digest Item records still keep the latest selection state in fields such as
`selection_status`, `selection_score`, `selection_reason`, `selection_result`,
and `selection_evaluated_at`. The history table is append-only and is used to
inspect how decisions were made over time.

History rows are written when the processing orchestrator evaluates selection:

- `pre_content`: before article content is fetched
- `post_content`: after article content is available

`manual_reevaluation` is reserved as a future phase value for explicit manual
re-evaluation workflows.

Each history row stores the phase, status, score, reason, matched positive and
negative keywords, lightweight input metadata, and a summary of selection
threshold settings. Full article content is not duplicated into
`selection_evaluations`; only presence flags and character lengths are stored.

This is separate from command run logging. It records selection decisions, not
Artisan command execution history.

## Command Run Logging

Key digestpipe Artisan command runs are stored in the
`digestpipe_command_runs` table.

Currently instrumented commands:

- `digestpipe:feeds:fetch`
- `digestpipe:items:enqueue-processing`

Each command run records start, completion or failure, duration, command
arguments, a JSON result summary, and a safe error message when an uncaught
exception fails the command. Exceptions are not swallowed by the recorder.

`digestpipe:feeds:fetch` stores counts such as created items, duplicates,
failed feed items, and failed feeds. Feed-level failures that the command already
handles remain part of a completed command run and are reflected in the summary.

`digestpipe:items:enqueue-processing` stores candidate counts, queued/planned
job counts, skipped counts, selection counts, and source summary data.

This helps diagnose scheduler gaps, Laravel Cloud hibernation, stale scheduler
mutex issues, and failed batch execution. If scheduled tasks stop running due to
a stale scheduler mutex, `php artisan schedule:clear-cache` remains the recovery
and debugging tool; command run logging only records observed executions.

Command run logging is separate from selection evaluation history. Old command
run rows may need pruning later, for example by retaining only the last 30 or 90
days.

## Laravel Cloud Deployment Status

The Filament dashboard includes a Cloud Status widget for the latest Laravel
Cloud deployment.

Required environment variables:

```env
LARAVEL_CLOUD_API_TOKEN=
LARAVEL_CLOUD_ENVIRONMENT_ID=
```

The widget calls the Laravel Cloud deployment list endpoint for the configured
environment and displays the latest deployment status, branch, commit metadata,
timestamps, and failure reason when available. The result is cached briefly to
avoid calling the Laravel Cloud API on every dashboard render.

This phase only observes deployment status. It does not read environment
metrics, database metrics, logs, or execute Laravel Cloud commands. Do not commit
the API token.

## Selection Rollback

Use the selection rollback command when selection rules changed and skipped
items need to be evaluated again from the application runtime:

```bash
php artisan digestpipe:selection:rollback --source=hacker_news --status=skipped
```

This command is intentionally narrow. It requires a source key, supports only
`--status=skipped`, and resets only selection fields back to a pending state.
Items that already moved into article content fetching or analysis are skipped.

Inspect the target records first:

```bash
php artisan digestpipe:selection:rollback --source=hacker_news --status=skipped --dry-run
```
