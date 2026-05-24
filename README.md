# digestpipe

Distilled Information Gateway for News Feed Translation Pipeline.

A tiny pipe that turns noisy news feeds into clean Japanese signal.

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
docker compose exec -T php-cli php artisan digestpipe:items:enqueue-content-fetch
docker compose exec -T php-cli php artisan digestpipe:items:enqueue-processing
docker compose exec -T php-cli php artisan queue:work --stop-when-empty
```

`digestpipe:items:enqueue-content-fetch` supports `--limit`, `--dry-run`, and `--source`.

`digestpipe:items:enqueue-processing` supports `--limit`, `--dry-run`, `--only=translation`, and `--only=summary`.

digestpipe treats RSS items as discovery signals, not always as full article content. For Hacker News RSS, `link` is the source article URL, `comments` is the Hacker News discussion URL, and `description` usually contains only a Comments link. The content fetch pipeline enriches items by fetching and extracting source article text before AI translation and summarization. Discussion/comment extraction is planned separately.

## AI Processing Driver

The translation and summary pipeline supports a safe fake driver and an OpenAI-backed driver.

```dotenv
DIGESTPIPE_AI_DRIVER=fake
DIGESTPIPE_AI_BATCH_LIMIT=3
DIGESTPIPE_AI_DAILY_LIMIT=30
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4o-mini
OPENAI_REQUEST_TIMEOUT=60
OPENAI_MAX_RETRIES=2
```

Use `DIGESTPIPE_AI_DRIVER=fake` for tests and safe local development. Do not commit real API keys.

For a cautious first OpenAI run, keep limits low:

```dotenv
DIGESTPIPE_AI_DRIVER=openai
DIGESTPIPE_AI_BATCH_LIMIT=1
DIGESTPIPE_AI_DAILY_LIMIT=10
OPENAI_MODEL=gpt-5.5
OPENAI_REQUEST_TIMEOUT=120
OPENAI_MAX_RETRIES=2
```

For lower-cost local trials:

```dotenv
DIGESTPIPE_AI_DRIVER=openai
DIGESTPIPE_AI_BATCH_LIMIT=3
DIGESTPIPE_AI_DAILY_LIMIT=30
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

## Laravel Cloud Assumptions

This project is designed to be deployed to Laravel Cloud.

Persistent user-generated files must not be stored on the local filesystem.
Use the S3 filesystem driver instead.
Logs should be emitted to stderr, and database, cache, session, queue, and storage backends must be configurable through environment variables.

Docker Compose and nginx configuration are intended for local development only.
The production environment must not depend on custom nginx configuration or mutable container state.
