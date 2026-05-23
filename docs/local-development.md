# Local Development Environment

The Docker Compose setup in this repository is intended for local development only. Production deployment targets Laravel Cloud, and application settings should be configured through environment variables.

## Services

- `php-cli`: Runs Composer, Artisan, PHPUnit, Pest, PHPStan, and PHP-CS-Fixer.
- `php-fpm`: Provides the PHP-FPM runtime for the Laravel web application.
- `nginx`: Forwards HTTP requests to `php-fpm`.
- `postgres`: Local PostgreSQL database.
- `valkey`: Redis-compatible cache and session backend.
- `minio`: S3-compatible object storage.

## Common Commands

```bash
docker compose build
docker compose up -d
docker compose run --rm php-cli composer install
cp src/.env.example src/.env
docker compose run --rm php-cli php artisan key:generate
docker compose run --rm php-cli php artisan migrate
docker compose run --rm php-cli php artisan test
docker compose run --rm php-cli ./vendor/bin/pest
docker compose run --rm php-cli ./vendor/bin/phpstan analyse
docker compose run --rm php-cli ./vendor/bin/php-cs-fixer fix --dry-run --diff
```

The web application is available at `http://localhost:8080`.

The MinIO console is available at `http://localhost:9001`. The sample local credentials are `minioadmin` / `minioadmin`.

## Laravel Cloud Assumptions

- Persistent user-generated files must not be stored on the local filesystem. Use the S3 filesystem driver instead.
- Logs should be emitted to `stderr`.
- Database, cache, session, queue, and storage backends must be configurable through environment variables.
- Deploy-time persistent operations should primarily be handled through migrations. Do not rely on mutable container state.
