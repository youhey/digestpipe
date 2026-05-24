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
- `postgres`
    - PostgreSQL database
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
DB_CONNECTION=pgsql
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

- PostgreSQL
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

- `ニュース記事アイテム`
- `RSS フィード情報源`
- `分析結果 JSON`
- `構造化した JSON`
- `本文取得`
- `待ち行列に登録`
- `dispatch`
- `Status Field`

Do not force technical terms into Japanese when the English term is clearer or already used in the codebase. Terms such as `OpenAI Responses API`, `JSON Schema`, `dry-run`, `Status`, and `Payload` may remain in English.

For public properties, prefer a short one-line `@var` comment that gives the type and meaning.

For public methods, include a short behavior summary and add `@param`, `@return`, and `@throws` tags when they clarify the contract. Make sure the summary preserves important preconditions. For example, a summary job that only handles translated items should be described as handling translated news items, not merely as summarizing news items.

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
postgres
valkey
minio
```

Do not use Docker Compose as a production deployment model. It is only for local development.

## Database

Use PostgreSQL as the default database.

Migrations should be database-portable where reasonable, but PostgreSQL compatibility is the priority.

Avoid MySQL-specific SQL.

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

The primary AI pipeline analyzes source content and stores structured digest JSON. digestpipe is not primarily a translation application; downstream applications can translate, rewrite, narrate, or personalize the structured output.

Translation and summary jobs remain available as legacy compatibility paths for now, but they should not be treated as the future primary pipeline.

The fake AI driver must remain available for tests and safe local development.

OpenAI-backed processing is selected through Laravel config and environment variables, not hard-coded in jobs.

```env
DIGESTPIPE_AI_DRIVER=fake
DIGESTPIPE_AI_BATCH_LIMIT=3
DIGESTPIPE_AI_DAILY_LIMIT=30
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

Analysis input should prefer extracted `article_content_text`, then meaningful `excerpt`, then title-only fallback. Do not send raw HTML or large article bodies to AI services. Translation and summary compatibility paths should follow the same input hygiene.

Use `digestpipe:items:enqueue-processing` as the primary item processing orchestrator. It is state-aware and dispatches only one next valid job per item in this order: article content fetch, article analysis. Do not manually enqueue translation or summary as part of the normal pipeline; use explicit legacy stages only when compatibility testing requires them.

Treat an item as ready for downstream digest use only when `analysis_status=completed` and `analysis_json` is present. Do not enqueue legacy translation or summary as a default follow-up after completed analysis.

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
postgres:
  environment:
    POSTGRES_DB: digestpipe
    POSTGRES_USER: digestpipe
    POSTGRES_PASSWORD: digestpipe

minio:
  environment:
    MINIO_ROOT_USER: minioadmin
    MINIO_ROOT_PASSWORD: minioadmin
```

However, the database, cache, session, queue, storage, and logging backends used by Laravel are application-layer settings. They must be defined in `src/.env.example` / `src/.env`.

Even when values overlap, such as `POSTGRES_DB` and `DB_DATABASE`, they belong to different layers.

```txt
POSTGRES_DB   # Initial database name created by the PostgreSQL container
DB_DATABASE   # Database name used by the Laravel application connection
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
DB_HOST=postgres
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
