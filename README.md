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
