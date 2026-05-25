# digestpipe

Distilled Information Gateway for News Feed Transformation Pipeline.

A tiny pipeline that turns noisy information sources into structured digest JSON.

## Structure

```txt
.
├── 🗂️ docs/                 # Development documentation
├── 🗂️ docker/               # Dockerfiles and container configuration
├── 🗂️ src/                  # Laravel application source
├── 📄 docker-compose.yml    # Local development environment
├── 📄 AGENTS.md
└── 📄 README.md
```

The Laravel application must live under `src/`.
Laravel framework files should not be placed directly in the repository root.

## Local Setup

On the first run, build the Docker images and install the application dependencies.

```bash
make build
make up
```

The web application is available at `http://localhost:8080`.

The MinIO console is available at `http://localhost:9001`.
The sample local credentials are `minioadmin` / `minioadmin`.

## Development Workflow

Use the Makefile as the main entrypoint for local development.

Typical workflow:

```bash
make build   # First-time setup and container build
make up      # Start services and prepare the app
make test    # Run PHPUnit
make lint    # Run PHPStan and PHP-CS-Fixer dry-run
make fix     # Apply PHP-CS-Fixer
make down    # Stop services
```

Node.js, npm, and Vite should be run through the node service.

```bash
make front-build
```

make test and make lint should pass before committing.

make destroy removes containers, images, and volumes. Use it only when you want to reset the local environment completely.

## Batch Commands

Run application batch commands through the `php-cli` service.

```bash
docker compose exec -T php-cli php artisan digestpipe:feeds:fetch
docker compose exec -T php-cli php artisan digestpipe:items:enqueue-processing
docker compose exec -T php-cli php artisan queue:work --stop-when-empty
docker compose exec -T php-cli php artisan digestpipe:digests:export --limit=20
```

Use `digestpipe:items:enqueue-processing` as the primary item processing orchestrator. The command is state-aware and dispatches only the next valid job for each item: article content fetch, then article analysis. The analysis output is structured digest JSON for downstream applications.

`digestpipe:items:enqueue-processing` supports `--limit`, `--dry-run`, `--source`, and `--stage=content|analysis`. `--limit` limits the number of jobs dispatched in one command run.

A news item is ready for downstream digest use when `analysis_status=completed` and `analysis_json` is present.

digestpipe treats RSS items as discovery signals, not always as full article content. For Hacker News RSS, `link` is the source article URL, `comments` is the Hacker News discussion URL, and `description` usually contains only a Comments link. The content fetch pipeline enriches items by fetching and extracting source article text before AI analysis. Discussion/comment extraction is planned separately.

## Structured Digest Export

The primary output of digestpipe is structured digest JSON. Each exported digest wraps source metadata, article metadata, processing metadata, and the stored `analysis_json`.

Downstream applications can translate, rewrite, narrate, personalize, rank, group, or combine these digest records. Raw extracted article content is not exported by default.

digestpipe exposes this output through an Artisan command and a private read-only Article JSON API.

```bash
docker compose exec -T php-cli php artisan digestpipe:digests:export --limit=20
docker compose exec -T php-cli php artisan digestpipe:digests:export --source=hacker_news --format=json
docker compose exec -T php-cli php artisan digestpipe:digests:export --topic=technology --format=jsonl
docker compose exec -T php-cli php artisan digestpipe:digests:export --from=2026-05-01 --to=2026-05-24
```

Supported filters are `--source`, `--topic`, `--content-type`, `--from`, `--to`, and `--limit`. Supported formats are `json` and `jsonl`. The command only exports records where `analysis_status=completed` and `analysis_json` is present.

## API

The private read-only Article JSON API is documented in [docs/api.md](docs/api.md).

## AI Processing Driver

The primary AI pipeline analyzes source content and stores structured digest JSON. Downstream applications can later translate, rewrite, narrate, or personalize the structured output.

The AI pipeline supports a safe fake driver and an OpenAI-backed driver.

```dotenv
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

Use `DIGESTPIPE_AI_DRIVER=fake` for tests and safe local development. Do not commit real API keys.

For a cautious first OpenAI run, keep limits low:

```dotenv
DIGESTPIPE_AI_DRIVER=openai
DIGESTPIPE_ANALYSIS_BATCH_LIMIT=1
DIGESTPIPE_ANALYSIS_DAILY_LIMIT=10
OPENAI_MODEL=gpt-5.5
OPENAI_REQUEST_TIMEOUT=120
OPENAI_MAX_RETRIES=2
```

For lower-cost local trials:

```dotenv
DIGESTPIPE_AI_DRIVER=openai
DIGESTPIPE_ANALYSIS_BATCH_LIMIT=3
DIGESTPIPE_ANALYSIS_DAILY_LIMIT=30
OPENAI_MODEL=gpt-4o-mini
OPENAI_REQUEST_TIMEOUT=60
OPENAI_MAX_RETRIES=2
```

Automated tests must not call the real OpenAI API. Use HTTP fakes or the fake AI driver.

## Database Reset Behavior

`make up` runs `php artisan migrate:refresh` and `php artisan db:seed`.

This means the local database schema is refreshed when starting the app. Do not store important local data in the development database.

## Local Defaults

`src/.env.example` is configured for the local Docker Compose environment.
The actual `src/.env` file should be created locally and must not be committed.
Laravel reads application settings from `src/.env`; Docker Compose only defines the local containers and middleware services.

```dotenv
DB_CONNECTION=pgsql
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=database
FILESYSTEM_DISK=s3
LOG_CHANNEL=stderr
```

The local stack uses PostgreSQL for the database, Valkey for cache and session storage, and MinIO for object storage.
SQLite is not used as the default database for this project.

Default local URLs:

- Web: `http://localhost:8080`
- Vite: `http://localhost:5173`
- MinIO Console: `http://localhost:9001`

## Environment Files

The root `.env` is used by Docker Compose for local port forwarding and middleware bootstrap values.

The `src/.env` file is used by the Laravel application.

Laravel application settings should live in `src/.env.example` and `src/.env`, not in `docker-compose.yml`.

## Laravel Cloud Detection Note

The Laravel application lives under `src/`.

A copy of `src/composer.lock` is kept at the repository root as a temporary Laravel Cloud framework detection workaround.
The authoritative Composer project remains `src/composer.json` / `src/composer.lock`.

If this workaround proves stable, the root-level lock file may be generated automatically by CI in a future change.

## Laravel Cloud Assumptions

This project is designed to be deployed to Laravel Cloud.

Persistent user-generated files must not be stored on the local filesystem.
Use the S3 filesystem driver instead.
Logs should be emitted to stderr, and database, cache, session, queue, and storage backends must be configurable through environment variables.

Docker Compose and nginx configuration are intended for local development only.
The production environment must not depend on custom nginx configuration or mutable container state.
