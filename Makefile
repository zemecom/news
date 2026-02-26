# Executables variables
DOCKER_APP = docker compose exec -T app
COMPOSER   = $(DOCKER_APP) composer
ARTISAN    = $(DOCKER_APP) php artisan
NPM        = $(DOCKER_APP) npm

.PHONY: up down build dev npm-dev help logs docs-deps app worker
.PHONY: setup-local setup-ci migrate messaging-setup
.PHONY: test test-arch test-all acceptance smoke-api ci-check
.PHONY: analyze psalm psalm-taint lint lint-check rector rector-check validate audit
.PHONY: crawl process-once queue serving media-backfill

# --- Main Commands ---

up:
	@# Start the application in detached mode (background)
	docker compose up -d

down:
	@# Stop and remove containers, networks, images, and volumes
	docker compose down

build:
	@# Build or rebuild services
	docker compose build

dev:
	@# Run development servers concurrently (server, queue, logs, vite) inside Docker
	$(DOCKER_APP) npx concurrently -c "#93c5fd,#c4b5fd,#fb7185,#fdba74" \
		"sh docker/bin/start-octane.sh" \
		"php artisan queue:listen --tries=1 --timeout=0" \
		"php artisan pail --timeout=0" \
		"npm run dev" \
		--names=server,queue,logs,vite \
		--kill-others

npm-dev:
	@# Run only frontend dev server inside Docker
	$(NPM) run dev

# --- Setup & Configuration ---

setup-local:
	@# Install dependencies and setup environment for local development via Docker
	docker compose up -d --build
	$(COMPOSER) install
	$(NPM) install
	cp .env.example .env || true
	$(ARTISAN) key:generate
	$(ARTISAN) migrate
	$(ARTISAN) storage:link || true
	@echo "Setup complete! Run 'make dev' to start."

setup-ci:
	@# Setup environment for CI (headless, no server start) inside Docker
	$(COMPOSER) install --no-interaction --prefer-dist
	$(NPM) install
	cp .env.example .env || true
	$(ARTISAN) key:generate
	$(ARTISAN) migrate:fresh --seed --force
	$(ARTISAN) storage:link || true

migrate:
	@# Run database migrations for the application
	$(ARTISAN) migrate --force

db-reset:
	@# Drop all tables, run migrations, and seed the database
	$(ARTISAN) migrate:fresh --seed --force

messaging-setup:
	@# Setup RabbitMQ topology (exchanges, queues, bindings)
	$(ARTISAN) news:messaging:setup

setup-hooks:
	@# Setup git hooks path
	git config core.hooksPath .githooks

# --- Testing & Quality Assurance ---

test:
	@# Run PHPUnit tests inside the container
	$(COMPOSER) test

test-arch:
	@# Run architecture tests (Pest) to verify architectural rules
	$(COMPOSER) test:arch

test-all: smoke-api test test-arch acceptance
	@# Run all test suites: smoke, unit/feature, architecture, and acceptance

acceptance:
	@# Run acceptance tests
	$(COMPOSER) test:acceptance

smoke-api:
	@# Run basic smoke tests against the API (inside container)
	$(DOCKER_APP) sh scripts/smoke-api.sh http://127.0.0.1:8000

ci-check: validate audit lint-check analyze psalm-taint test test-arch
	@# Run all CI pipeline checks (validate, audit, lint, phpstan, psalm, tests)

# --- Static Analysis & Linting ---

analyze:
	@# Run PHPStan static analysis
	$(COMPOSER) analyze

psalm:
	@# Run Psalm static analysis
	$(COMPOSER) psalm

psalm-taint:
	@# Run Psalm taint analysis (security check)
	$(COMPOSER) psalm:taint

lint:
	@# Fix coding standards using Pint (auto-fix)
	$(COMPOSER) lint

lint-check:
	@# Check coding standards using Pint (dry-run)
	$(COMPOSER) lint:check

rector:
	@# Run Rector to automatically refactor code
	$(COMPOSER) rector

rector-check:
	@# Check code for Rector refactoring opportunities (dry-run)
	$(COMPOSER) rector:check

validate:
	@# Validate composer.json and lock file
	$(COMPOSER) qa:validate

audit:
	@# Audit composer dependencies for security vulnerabilities
	$(COMPOSER) qa:audit

# --- Application Operations ---

app:
	@# Enter the app container shell
	docker compose exec -it app sh

worker:
	@# Enter the worker container shell
	docker compose exec -it worker sh

crawl:
	@# Run the crawler command manually
	$(ARTISAN) news:crawl

media-backfill:
	@# Backfill media assets for existing news items
	$(ARTISAN) news:media:backfill

process-once:
	@# Process a single queued job from RabbitMQ
	$(ARTISAN) queue:work --stop-when-empty --max-jobs=1 --queue=crawler_tasks,intelligence_tasks,media_tasks --tries=3

queue:
	@# Listen to the queue inside Docker
	$(ARTISAN) queue:listen --tries=1 --timeout=0

serve:
	@# Serve the application inside Docker
	$(DOCKER_APP) sh docker/bin/start-octane.sh

logs:
	@# View output from containers
	docker compose logs -f --tail=200

docs-deps:
	@# Generate documentation maps (docs/PROJECT_STRUCTURE.md + docs/PROJECT_INTERFACE.md) using Context Hub Generator
	ctx generate --no-interaction

# --- Help ---

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
