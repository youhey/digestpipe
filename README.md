# digestpipe

Distilled Information Gateway for News Feed Translation Pipeline.

A tiny pipe that turns noisy news feeds into clean Japanese signal.

## Structure

```txt
.
├── 🗂️ docker/               # Development documentation
├── 🗂️ docs/                 # Dockerfiles and container configuration
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
docker compose build
docker compose run --rm php-cli composer install
docker compose run --rm node npm install
cp src/.env.example src/.env
docker compose run --rm php-cli php artisan key:generate
docker compose run --rm php-cli php artisan migrate
docker compose up -d
```

The web application is available at `http://localhost:8080`.

The MinIO console is available at `http://localhost:9001`.
The sample local credentials are `minioadmin` / `minioadmin`.

## Common Commands

Composer, Artisan, tests, static analysis, and formatting should be run through the php-cli service.

```bash
docker compose run --rm php-cli php artisan test
docker compose run --rm php-cli ./vendor/bin/pest
docker compose run --rm php-cli ./vendor/bin/phpstan analyse
docker compose run --rm php-cli ./vendor/bin/php-cs-fixer fix --dry-run --diff
docker compose run --rm php-cli ./vendor/bin/php-cs-fixer fix
```

Node.js, npm, and Vite should be run through the node service.

```bash
docker compose run --rm node npm install
docker compose run --rm node npm run build
docker compose run --rm node npm run dev -- --host 0.0.0.0
```

Local Defaults

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

## Laravel Cloud Assumptions

This project is designed to be deployed to Laravel Cloud.

Persistent user-generated files must not be stored on the local filesystem.
Use the S3 filesystem driver instead.
Logs should be emitted to stderr, and database, cache, session, queue, and storage backends must be configurable through environment variables.

Docker Compose and nginx configuration are intended for local development only.
The production environment must not depend on custom nginx configuration or mutable container state.
