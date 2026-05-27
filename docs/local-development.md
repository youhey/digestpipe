# Local Development Environment

The Docker Compose setup in this repository is intended for local development only.
Production deployment targets Laravel Cloud, and application settings should be configured through environment variables.

Local Laravel settings live in `src/.env`.
Create it from `src/.env.example`;
Docker Compose should only define the local containers and middleware services.

## Services

- `php-cli`: Runs Composer, Artisan, PHPUnit, Pest, PHPStan, PHP-CS-Fixer, and Composer audit.
- `php-fpm`: Provides the PHP-FPM runtime for the Laravel web application.
- `nginx`: Forwards HTTP requests to `php-fpm`.
- `node`: Runs Node.js, npm, and Vite commands.
- `mysql`: Local MySQL database.
- `valkey`: Redis-compatible cache and session backend.
- `minio`: S3-compatible object storage.

## App Key

APP_KEY must be left empty in src/.env.example.

```dotenv
APP_KEY=
```

Generate it during local setup:

```bash
docker compose run --rm php-cli php artisan key:generate
```

Do not commit a fixed APP_KEY.

## Common Commands

```bash
docker compose build
docker compose up -d
docker compose run --rm php-cli composer install
docker compose run --rm node npm install
cp src/.env.example src/.env
docker compose run --rm php-cli php artisan key:generate
docker compose run --rm php-cli php artisan migrate
docker compose run --rm php-cli php artisan test
docker compose run --rm php-cli ./vendor/bin/pest
docker compose run --rm php-cli ./vendor/bin/phpstan analyse
docker compose run --rm php-cli ./vendor/bin/php-cs-fixer fix --dry-run --diff
docker compose run --rm php-cli composer audit --no-interaction
docker compose run --rm node npm run build
docker compose run --rm node npm run dev -- --host 0.0.0.0
```

Prefer the Makefile for normal validation:

```bash
make test
make lint
```

`make lint` runs static and mechanical checks, including PHPStan, PHP-CS-Fixer dry-run, and Composer audit.

The web application is available at `http://localhost:8080`.

The MinIO console is available at `http://localhost:9001`.
The sample local credentials are `minioadmin` / `minioadmin`.

## Laravel Cloud Assumptions

- Persistent user-generated files must not be stored on the local filesystem. Use the S3 filesystem driver instead.
- Logs should be emitted to `stderr`.
- Database, cache, session, queue, and storage backends must be configurable through environment variables.
- Deploy-time persistent operations should primarily be handled through migrations. Do not rely on mutable container state.
