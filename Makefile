
.PHONY: acceptance analyze audit build ci-check crawl dev docs-deps down help lint lint-check logs messaging-setup migrate process-once psalm psalm-taint queue rector rector-check serve setup-local smoke-api test test-all test-arch up validate

acceptance:
	@# Run acceptance tests
	docker compose exec -T app composer test:acceptance

ci-check: validate audit lint-check analyze psalm-taint test
	@# Run all CI pipeline checks (validate, audit, lint, phpstan, psalm, tests)

analyze:
	@# Run PHPStan static analysis
	docker compose exec -T app composer analyze

audit:
	@# Audit composer dependencies for security vulnerabilities
	docker compose exec -T app composer qa:audit

build:
	@# Build or rebuild services
	docker compose build app

crawl:
	@# Run the crawler command manually
	docker compose exec -T app php artisan news:crawl

dev:
	@# Run development servers concurrently (server, queue, logs, vite)
	npx concurrently -c "#93c5fd,#c4b5fd,#fb7185,#fdba74" \
		"php artisan serve" \
		"php artisan queue:listen --tries=1 --timeout=0" \
		"php artisan pail --timeout=0" \
		"npm run dev" \
		--names=server,queue,logs,vite \
		--kill-others

docs-deps:
	@# Generate business logic dependency documentation (docs/BUSINESS_LOGIC_DEPENDENCIES.md) using Context Hub Generator
	ctx generate --no-interaction

down:
	@# Stop and remove containers, networks, images, and volumes
	docker compose down

help:
	@# Show available commands
	@echo "Usage: make \033[36m<target>\033[0m"
	@echo ""
	@echo "Available commands:"
	@awk '/^[a-zA-Z0-9_-]+:/ { \
		target=$$1; \
		if (getline > 0) { \
			if (match($$0, /@# (.*)/)) { \
				desc=substr($$0, RSTART + 3, RLENGTH); \
				printf "  \033[36m%-20s\033[0m %s\n", substr(target, 1, length(target)-1), desc; \
			} \
		} \
	}' $(MAKEFILE_LIST)

lint:
	@# Fix coding standards using Pint (auto-fix)
	docker compose exec -T app composer lint

lint-check:
	@# Check coding standards using Pint (dry-run)
	docker compose exec -T app composer lint:check

logs:
	@# View output from containers
	docker compose logs -f --tail=200

messaging-setup:
	@# Setup RabbitMQ topology (exchanges, queues, bindings)
	docker compose exec -T app php artisan news:messaging:setup

migrate:
	@# Run database migrations for the application
	docker compose exec -T app php artisan migrate --force

process-once:
	@# Process a single news item from the queue
	docker compose exec -T app php artisan news:process --once

psalm:
	@# Run Psalm static analysis
	docker compose exec -T app composer psalm

psalm-taint:
	@# Run Psalm taint analysis (security check)
	docker compose exec -T app composer psalm:taint

queue:
	@# Listen to the queue
	php artisan queue:listen --tries=1 --timeout=0

rector:
	@# Run Rector to automatically refactor code
	docker compose exec -T app composer rector

rector-check:
	@# Check code for Rector refactoring opportunities (dry-run)
	docker compose exec -T app composer rector:check

serve:
	@# Serve the application on the PHP development server
	php artisan serve

setup-local:
	@# Install dependencies and setup environment for local development
	composer install
	cp .env.example .env || true
	php artisan key:generate
	php artisan migrate
	php artisan serve

smoke-api:
	@# Run basic smoke tests against the API
	sh scripts/smoke-api.sh

test:
	@# Run PHPUnit tests inside the container
	docker compose exec -T app composer test

test-all: smoke-api test test-arch acceptance
	@# Run all test suites: smoke, unit/feature, architecture, and acceptance

test-arch:
	@# Run architecture tests (Pest) to verify architectural rules
	docker compose exec -T app composer test:arch

up:
	@# Start the application in detached mode (background)
	docker compose up -d

validate:
	@# Validate composer.json and lock file
	docker compose exec -T app composer qa:validate
