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
