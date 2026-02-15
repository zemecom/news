.PHONY: up down build migrate logs messaging-setup crawl process-once serve queue dev setup-local test smoke-api test-arch acceptance test-all analyze lint lint-check rector rector-check psalm psalm-taint validate audit

# Infra
up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build app

migrate:
	docker compose exec -T app php artisan migrate --force

logs:
	docker compose logs -f --tail=200

messaging-setup:
	docker compose exec -T app php artisan news:messaging:setup

crawl:
	docker compose exec -T app php artisan news:crawl

process-once:
	docker compose exec -T app php artisan news:process --once

# Local dev
serve:
	php artisan serve

queue:
	php artisan queue:listen --tries=1 --timeout=0

dev:
	npx concurrently -c "#93c5fd,#c4b5fd,#fb7185,#fdba74" \
		"php artisan serve" \
		"php artisan queue:listen --tries=1 --timeout=0" \
		"php artisan pail --timeout=0" \
		"npm run dev" \
		--names=server,queue,logs,vite \
		--kill-others

setup-local:
	composer install
	cp .env.example .env || true
	php artisan key:generate
	php artisan migrate
	php artisan serve

# Tests
smoke-api:
	sh scripts/smoke-api.sh

test:
	docker compose exec -T app composer test

test-arch:
	docker compose exec -T app composer test:arch

acceptance:
	docker compose exec -T app composer test:acceptance

test-all: smoke-api test test-arch acceptance

# Static analysis / quality
analyze:
	docker compose exec -T app composer analyze

lint:
	docker compose exec -T app composer lint

lint-check:
	docker compose exec -T app composer lint:check

rector:
	docker compose exec -T app composer rector

rector-check:
	docker compose exec -T app composer rector:check

psalm:
	docker compose exec -T app composer psalm

psalm-taint:
	docker compose exec -T app composer psalm:taint

validate:
	docker compose exec -T app composer qa:validate

audit:
	docker compose exec -T app composer qa:audit
