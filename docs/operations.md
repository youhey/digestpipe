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
