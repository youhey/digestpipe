# Primary workflow:
#   make build
#   make up
#   make test
#   make lint
#   make fix
#   make down
#
# `destroy` removes Docker images and volumes.
# `clean` removes local generated files and dependencies.

.PHONY: build
build:
	@echo "🚀 Build the containers"
	-cp -n .env.example .env || true
	-mkdir -p src/build || true
	-cp -n src/.env.example src/.env || true
	-mkdir -p src/bootstrap/cache || true
	-mkdir -p src/storage/app/public || true
	-mkdir -p src/storage/framework/cache/data || true
	-mkdir -p src/storage/framework/sessions || true
	-mkdir -p src/storage/framework/testing || true
	-mkdir -p src/storage/framework/views || true
	-mkdir -p src/storage/debugbar || true
	-mkdir -p src/storage/logs || true
	-chmod 0777 src/bootstrap/cache || true
	-chmod 0777 src/storage || true
	-chmod 0777 src/storage/app || true
	-chmod 0777 src/storage/app/public || true
	-chmod 0777 src/storage/framework || true
	-chmod 0777 src/storage/framework/cache || true
	-chmod 0777 src/storage/framework/cache/data || true
	-chmod 0777 src/storage/framework/sessions || true
	-chmod 0777 src/storage/framework/testing || true
	-chmod 0777 src/storage/framework/views || true
	-chmod 0777 src/storage/debugbar || true
	-chmod 0777 src/storage/logs || true
	-rm -f src/bootstrap/cache/packages.php || true
	-rm -f src/bootstrap/cache/services.php || true
	-rm -f src/bootstrap/cache/config.php || true
	docker compose build
	@echo "✅ done"

.PHONY: up
up:
	@echo "🚀 Start the digestpipe App"
	docker compose up -d --remove-orphans --wait
	@echo "🔄 PHP Composer packages install"
	@$(MAKE) php-packages-install
	docker compose exec -T php-cli php artisan --version
	docker compose exec -T php-cli php artisan key:generate --silent --quiet --no-ansi --no-interaction
	docker compose exec -T php-cli php artisan migrate:refresh --no-interaction
	docker compose exec -T php-cli php artisan db:seed --no-interaction
	@echo "-------------------------"
	@$(MAKE) front-build
	@echo "✅ done"

.PHONY: down
down:
	@echo "🚀 Down the digestpipe App"
	docker compose down --remove-orphans
	@echo "✅ done"

.PHONY: destroy
destroy:
	@echo "🚀 Destroy the digestpipe App"
	docker compose down --rmi all --volumes --remove-orphans
	@echo "✅ done"

.PHONY: clean
clean:
	@echo "🚀 Clean up the local env"
	docker compose down --rmi all
	-rm -rf src/build || true
	-rm -rf src/vendor || true
	-rm -rf src/node_modules || true
	-rm -rf src/storage/framework/cache/data/* || true
	-rm -f src/storage/framework/sessions/* || true
	-rm -rf src/storage/framework/testing/disks || true
	-rm -f src/storage/framework/testing/* || true
	-rm -f src/storage/framework/views/* || true
	-rm -f src/storage/debugbar/* || true
	-rm -f src/storage/logs/* || true
	-rm -f src/bootstrap/cache/packages.php || true
	-rm -f src/bootstrap/cache/services.php || true
	-rm -f src/bootstrap/cache/config.php || true
	@echo "✅ done"

.PHONY: test
test: phpunit

.PHONY: lint
lint: phpstan php-dry-run-cs

.PHONY: fix
fix: php-fix-cs

.PHONY: phpunit
phpunit:
	@echo "📝 run test"
	@docker compose exec -T php-cli php artisan test --parallel --recreate-databases --drop-databases --display-deprecations --display-warnings
	@echo "✅ Tests passed 👍"

.PHONY: phpstan
phpstan:
	@echo "🧐 run lint (PHPStan)"
	@docker compose exec -T php-cli php vendor/bin/phpstan analyse --ansi --memory-limit=2G
	@echo "✅ PHPStan tests passed 👍"

.PHONY: php-dry-run-cs
php-dry-run-cs:
	@echo "🧐 run lint (dry run PHP CS Fixer)"
	@docker compose exec -T php-cli php vendor/bin/php-cs-fixer fix --dry-run --ansi --show-progress=dots --verbose --diff
	@echo "✅ PHP CS Fixer dry run tests passed 👍"

.PHONY: php-fix-cs
php-fix-cs:
	@echo "🧐 run fix (PHP CS Fixer)"
	@docker compose exec -T php-cli php vendor/bin/php-cs-fixer fix --ansi

.PHONY: php-packages-install
php-packages-install:
	@echo "🧑‍🏫 composer install"
	@docker compose exec -T php-cli composer install --no-interaction --prefer-dist --no-progress

.PHONY: php-packages-update
php-packages-update:
	@echo "🧑‍🏫 composer update"
	@docker compose exec -T php-cli composer update --no-interaction --prefer-dist --no-progress

.PHONY: php-packages-audit
php-packages-audit:
	@echo "🧑‍🏫 composer audit"
	@docker compose exec -T php-cli composer audit --no-interaction

.PHONY: php-composer-selfupdate
php-composer-selfupdate:
	@echo "🧑‍🏫 composer selfupdate"
	@docker compose exec -T php-cli composer selfupdate --no-interaction --no-progress

.PHONY: front-build
front-build:
	@echo "🧑‍🏫 npm ci && build"
	@docker compose exec -T node npm ci
	@docker compose exec -T node npm run build

.PHONY: front-audit
front-audit:
	@echo "🧑‍🏫 npm audit"
	@docker compose exec -T node npm audit

.PHONY: front-security-fix
front-security-fix:
	@echo "🧑‍🏫 npm audit fix"
	@docker compose exec -T node npm audit fix
