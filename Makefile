# The containers write to the bind-mounted project as the host user.
export HOST_UID := $(shell id -u)
export HOST_GID := $(shell id -g)

DC  = docker compose
PHP = $(DC) exec -T php

# First release with the fixes for CVE-2026-109618 and CVE-2026-109619 (ADR-004).
PHP_MIN_VERSION = 8.4.27

.DEFAULT_GOAL := help

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

init: ## Build, start everything, install dependencies and load sample positions
	$(DC) build --pull
	$(DC) up -d php caddy mongo rabbitmq
	$(PHP) composer install --no-interaction --no-progress
	$(MAKE) setup
	$(DC) --profile worker up -d worker-events worker-enrichment
	-@$(MAKE) --no-print-directory security
	@echo "\n  Ready: http://localhost:8080  ·  RabbitMQ: http://localhost:15673 (guest/guest)\n"

setup: ## Create MongoDB indexes, load the sample positions and build the CSS
	$(PHP) bin/console app:mongo:setup
	$(PHP) bin/console app:positions:seed
	$(MAKE) --no-print-directory assets

assets: ## Build the CSS (Tailwind standalone binary, no Node.js)
	$(PHP) bin/console tailwind:build

demo: ## Submit sample applications (enriched asynchronously by the workers)
	$(PHP) bin/console app:demo:applications

css: ## Rebuild the CSS on every template change
	$(DC) exec php bin/console tailwind:build --watch

up: ## Start containers (including the workers)
	$(DC) --profile worker up -d

down: ## Stop containers
	$(DC) --profile worker down

logs: ## Follow the workers' logs
	$(DC) logs -f worker-events worker-enrichment

sh: ## Shell inside the PHP container
	$(DC) exec php bash

test: assets ## Run the whole test suite (pages render the built CSS)
	$(PHP) vendor/bin/phpunit --no-coverage

qa: ## Code style and static analysis
	$(PHP) composer qa

security: ## Fail if the PHP runtime is older than the last security release
	@$(PHP) php -r 'exit(version_compare(PHP_VERSION, "$(PHP_MIN_VERSION)", ">=") ? 0 : 1);' \
		&& echo "  PHP runtime is patched (>= $(PHP_MIN_VERSION))." \
		|| (echo "  WARNING: PHP $$($(PHP) php -r 'echo PHP_VERSION;') is older than $(PHP_MIN_VERSION) (CVE-2026-109618, CVE-2026-109619). Run 'make init' again once the patched image is published."; exit 1)

hooks: ## Install the git pre-commit hook
	git config core.hooksPath resources/git/hooks

.PHONY: help init setup assets demo css up down logs sh test qa security hooks
