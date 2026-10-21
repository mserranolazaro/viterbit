DC  = docker compose
PHP = $(DC) exec -T php

.DEFAULT_GOAL := help

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

init: ## Build, start everything, install dependencies and load sample positions
	$(DC) build
	$(DC) up -d php caddy mongo rabbitmq
	$(PHP) composer install --no-interaction --no-progress
	$(MAKE) setup
	$(DC) --profile worker up -d worker
	@echo "\n  Ready: http://localhost:8080  ·  RabbitMQ: http://localhost:15673 (guest/guest)\n"

setup: ## Create MongoDB indexes, load the sample positions and build the CSS
	$(PHP) bin/console app:mongo:setup
	$(PHP) bin/console app:positions:seed
	$(PHP) bin/console tailwind:build

demo: ## Submit sample applications (enriched asynchronously by the worker)
	$(PHP) bin/console app:demo:applications

css: ## Rebuild the CSS on every template change
	$(DC) exec php bin/console tailwind:build --watch

up: ## Start containers (including the worker)
	$(DC) --profile worker up -d

down: ## Stop containers
	$(DC) --profile worker down

logs: ## Follow the worker logs
	$(DC) logs -f worker

sh: ## Shell inside the PHP container
	$(DC) exec php bash

test: ## Run the whole test suite
	$(PHP) vendor/bin/phpunit --no-coverage

qa: ## Code style and static analysis
	$(PHP) composer qa

hooks: ## Install the git pre-commit hook
	git config core.hooksPath resources/git/hooks

.PHONY: help init setup demo css up down logs sh test qa hooks
