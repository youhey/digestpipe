# AGENT.md

This document describes the development conventions and constraints for agents working on this repository.

## Project Overview

`digestpipe` is a small Laravel application designed to collect trusted news feeds, translate and summarize them, and expose the processed digest data through a JSON API.

Full name:

> Distilled Information Gateway for News Feed Translation Pipeline

Short description:

> A tiny pipe that turns noisy news feeds into clean Japanese signal.

The application is intended to be deployed to Laravel Cloud. The local development environment should approximate the Laravel Cloud runtime where practical, while remaining simple and reproducible with Docker Compose.

## Repository Layout

The repository root should remain small and focused.

```txt
docs/                 # Development documents
docker/               # Dockerfiles and container configuration
src/                  # Laravel application source
docker-compose.yml    # Local development environment
README.md
AGENT.md
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

The command and test container should use `php-cli`.

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

Common commands should be available from the `php-cli` service, for example:

```bash
docker compose run --rm php-cli php artisan test
docker compose run --rm php-cli ./vendor/bin/pest
```

## Static Analysis

Use PHPStan for static analysis.

PHPStan should be configured in the Laravel application under `src/`.

A typical command should be available:

```bash
docker compose run --rm php-cli ./vendor/bin/phpstan analyse
```

Prefer fixing issues rather than suppressing them.

If a suppression is necessary, keep it narrow and documented.

## Code Style

Use PHP-CS-Fixer for PHP formatting and coding style checks.

Typical commands should be available:

```bash
docker compose run --rm php-cli ./vendor/bin/php-cs-fixer fix --dry-run --diff
docker compose run --rm php-cli ./vendor/bin/php-cs-fixer fix
```

Keep formatting rules practical and Laravel-friendly.

Do not introduce broad style changes unrelated to the current task.

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
