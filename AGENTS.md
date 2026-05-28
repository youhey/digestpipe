# AGENTS.md

This document describes the development conventions and constraints for agents working on this repository.

## Project Overview

`digestpipe` is a small Laravel application designed to collect noisy information sources and turn them into structured digest JSON.

Full name:

> Distilled Information Gateway for News Feed Transformation Pipeline

Short description:

> A tiny pipeline that turns noisy information sources into structured digest JSON.

The application is intended to be deployed to Laravel Cloud. The local development environment should approximate the Laravel Cloud runtime where practical, while remaining simple and reproducible with Docker Compose.

## Repository Layout

The repository root should remain small and focused.

```txt
docs/                 # Development documents
docker/               # Dockerfiles and container configuration
src/                  # Laravel application source
docker-compose.yml    # Local development environment
README.md
AGENTS.md
```

Laravel application files must be placed under `src/`.

Do not place Laravel framework files directly in the repository root.

## Target Runtime

The production deployment target is Laravel Cloud.

Design the application with the following assumptions:

- Application containers are ephemeral.
- Local filesystem must not be used for persistent user data.
- Logs should be emitted to stdout/stderr.
- Environment variables are managed by the platform.
- Database, cache, session, queue, and storage backends must be configurable through environment variables.
- Build-time and deploy-time concerns should remain separate.

## Local Development Stack

Use Docker Compose for local development.

Required services:

- `php-cli`
    - Composer
    - Artisan
    - PHPUnit
    - Pest
    - PHPStan
    - PHP-CS-Fixer
- `php-fpm`
    - Web runtime for Laravel
- `nginx`
    - Local HTTP frontend
- `node`
    - Node.js, npm, and Vite tooling
- `mysql`
    - MySQL database
- `valkey`
    - Redis-compatible cache and session backend
- `minio`
    - S3-compatible object storage

The web frontend should use:

```txt
nginx -> php-fpm -> Laravel
```

PHP commands and tests should use `php-cli`.
Node.js, npm, and Vite commands should use `node`.

## Application Defaults

The Laravel application should default to the following local development settings:

```env
DB_CONNECTION=mysql
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=database
FILESYSTEM_DISK=s3
LOG_CHANNEL=stderr
```

Use Valkey through Laravel's Redis-compatible configuration.

Use MinIO through Laravel's S3 filesystem driver.

Do not use SQLite for this project.

Do not use file-based cache or file-based sessions as the default environment.

## Environment Files

Never commit real environment files.

Ignored:

```txt
.env
.env.*
```

Tracked:

```txt
.env.example
```

The `src/.env.example` file should be suitable for local Docker Compose development.

It should include example values for:

- MySQL
- Valkey
- MinIO
- Laravel app URL
- Logging
- Queue
- Cache
- Session
- Mail, if needed

Do not include real secrets.

## Laravel Cloud Compatibility

When making application or infrastructure changes, prefer Laravel Cloud-compatible defaults.

Important rules:

- Do not persist uploads or generated files to `storage/app` in production paths.
- Use the S3 filesystem driver for persistent object storage.
- Use `stderr` logging.
- Keep queues, sessions, cache, and storage configurable through `.env`.
- Avoid relying on shell access or mutable container state.
- Avoid adding runtime dependencies that require unmanaged system daemons.
- Avoid assuming custom nginx behavior in production.

## Laravel Cloud Repository Detection

The root-level `composer.lock` is a Laravel Cloud detection workaround copied from `src/composer.lock`.

Do not treat the repository root as the Laravel application root. The Laravel app remains under `src/`.

Do not edit the root-level `composer.lock` as the authoritative dependency lock. Update dependencies in `src/`.

The root-level `composer.lock` is only a framework detection dummy for Laravel Cloud. It does not need to stay fully synchronized with `src/composer.lock` during normal dependency updates.

## Laravel Cloud MySQL

Laravel Cloud deployment should use Laravel MySQL for this project.

Use database environment variables injected by the attached Laravel MySQL resource. Do not add Laravel Cloud Serverless Postgres, Neon, SNI, SSL, or endpoint option workarounds unless the database platform changes again.

Custom Laravel Cloud environment variables should not override `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, or `DATABASE_URL` unless there is a clear operational reason.

## Build and Deploy Expectations

For Laravel Cloud, build-time tasks should include things such as:

```bash
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Deploy-time tasks should be limited to tasks such as:

```bash
php artisan migrate --force
```

Do not add deployment steps that rely on persistent local filesystem changes.

Avoid using the following as deployment assumptions:

```bash
php artisan storage:link
php artisan optimize:clear
php artisan queue:restart
```

## Testing

PHPUnit is the primary test runner.

Pest should also be installed and available for experimentation.

Prefer adding tests for application behavior when implementing features.

Use the Makefile for normal test execution:

```bash
make test
```

GitHub Actions CI runs on pull requests and pushes to `main`. It should validate Composer metadata, install dependencies under `src/`, run Composer audit, run MySQL migrations, run PHPUnit without parallel mode, run PHPStan, and run PHP-CS-Fixer dry-run.

CI must not deploy. Laravel Cloud remains responsible for deployment from `main`.

Use a feature branch and pull request workflow for changes intended for `main`: create a branch, push it, open a pull request, wait for CI to pass, merge into `main`, and let Laravel Cloud deploy from `main`.

Do not put real OpenAI API keys or other secrets in CI. Use the fake AI driver for automated checks.

Dependabot creates dependency update pull requests for Composer dependencies under `src/` and GitHub Actions workflows. Do not add auto-merge workflows for Dependabot unless explicitly requested.

Review security update pull requests early. Normal dependency update pull requests can be reviewed roughly monthly. Major updates should be reviewed manually and carefully.

Composer updates are managed in `src/`. The root-level `composer.lock` is only a Laravel Cloud detection workaround and is not the authoritative dependency lock.

## Static Analysis

Use PHPStan for static analysis.

PHPStan should be configured in the Laravel application under `src/`.

Use the Makefile for normal static analysis:

```bash
make lint
```

Prefer fixing issues rather than suppressing them.

If a suppression is necessary, keep it narrow and documented.

## Code Style

Use PHP-CS-Fixer for PHP formatting and coding style checks.

Use the Makefile for normal coding style checks and fixes:

```bash
make lint
make fix
```

Keep formatting rules practical and Laravel-friendly.

Do not introduce broad style changes unrelated to the current task.

## PHP Code Readability

Prefer explicit class structure over compact syntax when defining application services.

Do not use constructor property promotion for service dependencies. Declare class properties first, then assign them inside the constructor. This keeps member structure easy to scan.

Avoid direct global namespace references such as `\Throwable`, `\DOMDocument`, or `\InvalidArgumentException` in application code. Add `use` declarations at the top of the file instead, so dependencies are visible in one place.

Do not mark application classes as `final` unless there is a concrete technical reason. The default should remain extensible and easy to mock in tests.

Application classes should have a class-level PHPDoc summary. Public methods and public properties should also have concise PHPDoc comments that explain their role. Keep comments descriptive, not historical; do not add comments that merely restate a recent change.

### PHPDoc Comment Style

Write PHPDoc comments in Japanese for project application code unless surrounding code already has a stronger local convention.

Use comments to describe the role of the class, method, or public property in the current design. Do not describe recent changes, implementation history, or task phases.

Keep summaries short and concrete. Prefer project vocabulary such as:

- `Digest Item`
- `RSS フィード情報源`
- `分析結果 JSON`
- `構造化した JSON`
- `本文取得`
- `待ち行列に登録`
- `dispatch`
- `Status Field`

Do not force technical terms into Japanese when the English term is clearer or already used in the codebase. Terms such as `OpenAI Responses API`, `JSON Schema`, `dry-run`, `Status`, and `Payload` may remain in English.

For public properties, prefer a short one-line `@var` comment that gives the type and meaning.

For public methods, include a short behavior summary and add `@param`, `@return`, and `@throws` tags when they clarify the contract. Make sure the summary preserves important preconditions. For example, a job that only handles completed article content should be described as handling content-ready digest items, not merely as handling digest items.

`Constructor` is acceptable as the constructor summary when the parameters already make the dependency setup clear.

## Docker Guidelines

Keep Docker configuration under `docker/`.

Suggested structure:

```txt
docker/
  nginx/
    default.conf
  php/
    cli/
      Dockerfile
    fpm/
      Dockerfile
```

The Docker Compose file should be at the repository root.

Prefer explicit service names:

```txt
php-cli
php-fpm
nginx
mysql
valkey
minio
```

Do not use Docker Compose as a production deployment model. It is only for local development.

## Database

Use MySQL as the default database.

Migrations should be database-portable where reasonable, but MySQL compatibility is the priority.

Avoid PostgreSQL-specific SQL.

Avoid raw SQL unless necessary.

When raw SQL is necessary, document why.

## Cache, Session, and Queue

Use Valkey as the Redis-compatible backend for cache and session storage.

Default local settings:

```env
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=database
```

For small local development and initial production deployment, database queues are acceptable.

If Redis queues are introduced later, keep the queue connection configurable.

## AI Processing

The primary AI pipeline analyzes source content and stores structured digest JSON. digestpipe is not primarily a translation application; downstream applications can translate, rewrite, narrate, personalize, rank, group, or combine the structured output.

The fake AI driver must remain available for tests and safe local development.

OpenAI-backed processing is selected through Laravel config and environment variables, not hard-coded in jobs.

```env
DIGESTPIPE_AI_DRIVER=fake
DIGESTPIPE_ANALYSIS_MODEL=gpt-4o-mini
DIGESTPIPE_ANALYSIS_BATCH_LIMIT=10
DIGESTPIPE_ANALYSIS_DAILY_LIMIT=100
DIGESTPIPE_ANALYSIS_MAX_INPUT_CHARS=8000
DIGESTPIPE_ANALYSIS_OUTPUT_SCHEMA_VERSION=1.0
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4o-mini
OPENAI_REQUEST_TIMEOUT=60
OPENAI_MAX_RETRIES=2
```

Never commit real OpenAI API keys. Do not log API keys, full prompts, full article bodies, or raw OpenAI responses.

Automated tests must not call the real OpenAI API. Use Laravel HTTP fakes, mocked services, or the fake AI driver.

## Article Content Fetching

Treat RSS items as discovery signals. Do not assume RSS descriptions contain full article content.

For Hacker News RSS, `link` is the source article URL, `comments` is the Hacker News discussion URL, and `description` usually contains only a Comments link. Do not treat that description as article content, and do not implement Hacker News discussion/comment summarization unless explicitly requested.

Article content extraction must be deterministic and non-AI. Prefer modest DOM-based extraction from source article HTML, and do not add a headless browser unless there is a concrete requirement.

Analysis input should prefer extracted `article_content_text`, then meaningful `excerpt`, then title-only fallback. Do not send raw HTML or large article bodies to AI services.

Use `digestpipe:items:enqueue-processing` as the primary item processing orchestrator. It is state-aware and dispatches only one next valid job per item in this order: article content fetch, article analysis.

Treat an item as ready for downstream digest use only when `analysis_status=completed` and `analysis_json` is present.

Use `digestpipe:digests:export` and the private Article JSON API as read-only export surfaces for completed structured digest records. Exported records should include source metadata, article metadata, processing metadata, and `analysis_json`; do not export raw `article_content_text` by default.

## API Authentication

For private HTTP API access, digestpipe uses Laravel Sanctum personal access tokens. Do not add OAuth, login APIs, registration APIs, password reset flows, public user management screens, or custom plaintext API token columns for API authentication unless explicitly requested.

Manage API users and tokens from Artisan commands:

```bash
php artisan digestpipe:users:create-api-user user@example.test --name="DigestPipe User"
php artisan digestpipe:users:rotate-api-token user@example.test
```

Tokens should use the `digests:read` ability for read-only digest access. Future digest API routes should be protected with `auth:sanctum` and the `digests:read` ability, for example `['auth:sanctum', 'abilities:digests:read']`.

Never log raw personal access tokens. Print newly created or rotated tokens only once in the command output, and do not commit generated tokens.

## Admin Panel

digestpipe has a private Filament admin panel foundation at `/admin`.

Admin login uses Google OAuth only. Do not add password login, registration, password reset, invitation flows, public account management, or role management unless explicitly requested.

Admin access is controlled by `DIGESTPIPE_ADMIN_ALLOWED_EMAILS`. The Google OAuth callback and `User::canAccessPanel()` must both enforce the allow-list. If the allow-list is empty, no user should be allowed into the admin panel.

`/_local/admin/login` is a local-only development helper for browser-based Filament UI debugging. It must remain disabled by default, must require `APP_ENV=local` or `APP_ENV=testing`, must require `DIGESTPIPE_ADMIN_DEV_LOGIN_ENABLED=true`, and must only log in `DIGESTPIPE_ADMIN_DEV_LOGIN_EMAIL` when that email also passes `DIGESTPIPE_ADMIN_ALLOWED_EMAILS` and `User::canAccessPanel()`.

Do not store or log Google OAuth access tokens, refresh tokens, authorization codes, client secrets, or raw provider payloads.

Feed Sources are DB-backed master data managed through the Filament admin panel. Do not reintroduce `feed_sources` under `config/digestpipe.php`; use the `feed_sources` table, `FeedSourceSeeder`, and `FeedSourceRepository`.

Selection Keywords are DB-backed master data managed through the Filament admin panel. Do not reintroduce `selection.positive_keywords` or `selection.negative_keywords` under `config/digestpipe.php`; use the `selection_keywords` table, `SelectionKeywordSeeder`, and `SelectionKeywordRepository`.

The Filament dashboard currently provides Phase 1 selection visibility and Phase 2 pipeline health visibility. It covers selection status, source breakdowns, keyword matches, recent selected/skipped Digest Items, article content status, analysis status, latest pipeline activity, and recent failed processing items. Keep command run history, scheduler run history, analysis insights, and source detail pages out of this dashboard unless explicitly requested.

Domain admin resources such as thresholds, Digest Item views, and analysis reports are intentionally deferred. When admin behavior changes, update `docs/admin.md` in the same task.

## Article JSON API

The private Article JSON API exposes completed article analysis records through read-only routes:

```txt
GET /api/articles
GET /api/articles/{id}
```

These routes must stay protected by `auth:sanctum` and `abilities:digests:read`. Do not add public unauthenticated access or write APIs unless explicitly requested.

The API response shape should stay aligned with `DigestExportItemBuilder` and `digestpipe:digests:export`. Do not expose raw `article_content_text`, prompts, provider raw responses, API keys, or secrets.

`GET /api/articles` supports `from`, `to`, `source`, and `limit`. The default window is the last 24 hours, using `published_at` with `fetched_at` fallback. The default limit is `100`, and the maximum limit is `500`.

`fields` filtering, pagination, topic filtering, source-specific metadata fetching, write APIs, daily digest generation, and Hacker News discussion/comment analysis are intentionally deferred.

## API Documentation

When API behavior changes, update `docs/api.md` in the same task. Do not document planned API features as current behavior.

## HTTP Client Smoke Tests

Root `tests/http/` contains PhpStorm HTTP Client smoke tests for manual integration checks.

Do not place these files under `src/tests`. Laravel application tests remain under `src/tests`.

Do not put secrets in committed HTTP Client files. Keep real API tokens in `tests/http/http-client.private.env.json`, which must stay ignored.

Do not wire these smoke tests into `make test` yet. Keep assertions broad, stable, and environment-independent.

## Object Storage

Use MinIO locally as an S3-compatible object storage service.

The Laravel application should use the S3 filesystem driver.

Required local environment variables should include:

```env
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=
AWS_ENDPOINT=
AWS_URL=
AWS_USE_PATH_STYLE_ENDPOINT=true
```

Do not rely on `local` filesystem storage for user-generated content.

## Logging

Use stderr logging by default.

```env
LOG_CHANNEL=stderr
```

Do not rely on `storage/logs` for production observability.

## Application Environment Configuration

Laravel application configuration must follow Laravel's standard `.env` loading behavior.

In this repository, the Laravel application lives under `src/`, so the application environment files are:

```txt
src/.env.example  # Sample local development settings. Committed.
src/.env          # Developer-specific local settings. Not committed.
```

Environment variables that control Laravel application behavior must be defined in `src/.env.example` / `src/.env`, not in `docker-compose.yml`.

Examples:

```txt
APP_*
DB_*
CACHE_*
SESSION_*
QUEUE_*
REDIS_*
FILESYSTEM_*
AWS_*
LOG_*
```

This keeps the project aligned with Laravel conventions and avoids duplicating the same settings across `docker-compose.yml` and `src/.env`. From an SSOT / DRY perspective, Laravel application settings belong in `src/.env`.

## Layer Responsibilities

`docker-compose.yml` defines the local development infrastructure layer.

It may define:

```txt
service image
build context
Dockerfile path
ports
volumes
networks
healthchecks
depends_on
middleware bootstrap settings
```

Settings required to bootstrap middleware containers may remain in `docker-compose.yml`.

Examples:

```yaml
mysql:
  environment:
    MYSQL_DATABASE: digestpipe
    MYSQL_USER: digestpipe
    MYSQL_PASSWORD: digestpipe
    MYSQL_ROOT_PASSWORD: root

minio:
  environment:
    MINIO_ROOT_USER: minioadmin
    MINIO_ROOT_PASSWORD: minioadmin
```

However, the database, cache, session, queue, storage, and logging backends used by Laravel are application-layer settings. They must be defined in `src/.env.example` / `src/.env`.

Even when values overlap, such as `MYSQL_DATABASE` and `DB_DATABASE`, they belong to different layers.

```txt
MYSQL_DATABASE  # Initial database name created by the MySQL container
DB_DATABASE     # Database name used by the Laravel application connection
```

This kind of duplication is acceptable because the variables configure different layers.

## Docker Compose Must Not Load `src/.env`

This project must not use Docker Compose `env_file` to load `src/.env`.

`src/.env` is the environment file for the Laravel application. The only intended usage is for Laravel itself to load it through Laravel's standard mechanism.

Docker Compose and other layers must not understand, load, or depend on `src/.env`.

Developers may still modify their local `src/.env` manually after copying it from `src/.env.example` when they need to change local application behavior.

## Laravel Cloud Compatibility

`src/.env.example` is committed as a sample configuration for local development.

In Laravel Cloud, production environment variables are managed by Laravel Cloud. Therefore, committing local development defaults to `src/.env.example` does not affect production behavior on Laravel Cloud.

It is acceptable for `src/.env.example` to include sample values required for the local Docker Compose environment.

Examples:

```env
DB_HOST=mysql
DB_DATABASE=digestpipe
DB_USERNAME=digestpipe
DB_PASSWORD=digestpipe

REDIS_HOST=valkey

AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin
AWS_BUCKET=digestpipe-local
AWS_ENDPOINT=http://minio:9000
```

These are sample credentials for local development only. They are not production secrets. In this context, committing them to `src/.env.example` is not considered a vulnerability.

## App Key Handling

Do not commit a fixed Laravel `APP_KEY`.

Leave it empty in `src/.env.example`.

```env
APP_KEY=
```

Local setup through the Makefile generates it:

```bash
make up
```

`APP_KEY` is used for encrypted cookies and encrypted application data, so it should be generated per environment, even for local development.

## Development Commands

Use the Makefile as the primary entrypoint for local development tasks.

Humans and agents should prefer these commands instead of calling Docker Compose directly:

- `make build`
- `make up`
- `make down`
- `make test`
- `make lint`
- `make fix`

Use raw `docker compose` commands only when debugging the local environment or when the Makefile does not provide the required operation.

The `php-cli` and `node` services are expected to be long-running services so that Makefile tasks can use `docker compose exec`.

## Makefile Policy

The Makefile is the canonical interface for local development operations.

Agents should prefer existing Makefile targets over direct `docker compose` commands.

Do not add new Makefile targets unless there is a repeated workflow that cannot be expressed with the existing targets.

Keep the command surface small. The primary commands are:

- `make build`
- `make up`
- `make down`
- `make test`
- `make lint`
- `make fix`

Use `make destroy` only when a full local reset is explicitly requested.

Do not replace `docker compose exec` with `docker compose run` for normal development commands unless there is a concrete reason. The `php-cli` and `node` services are intentionally long-running so they can be used as execution targets.

## Documentation

Use `docs/` for development notes and design documents.

Keep documentation short, practical, and close to the current implementation.

When introducing a new local service or tool, update the README or relevant document.

## Project Scripts

Project-level operational scripts live under the repository root `scripts/` directory.

Do not place Laravel application scripts under `src/scripts` unless they are part of the Laravel application itself.

Use `scripts/digestpipe-poll.sh` only as a local manual operation helper for polling Laravel's `GET /up` health endpoint during manual operation windows. Do not depend on the poller in tests, application logic, scheduler logic, or queue processing.

## Commit Hygiene

Keep commits focused.

Do not mix infrastructure, formatting, and feature work unless explicitly requested.

Prefer clear commit messages such as:

```txt
chore: scaffold local development stack
chore: add Laravel application skeleton
chore: configure testing and static analysis
```

## Safety Rules for Agents

Before making large changes:

1. Inspect the existing repository structure.
2. Preserve the intended root layout.
3. Keep Laravel files under `src/`.
4. Do not commit secrets.
5. Do not remove existing documentation unless asked.
6. Do not introduce unrelated dependencies.
7. Prefer small, reviewable changes.
8. Run relevant tests or explain why they could not be run.

If a requirement is ambiguous, choose the simplest Laravel Cloud-compatible implementation and document the assumption.
