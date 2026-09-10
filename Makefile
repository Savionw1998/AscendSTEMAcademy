# Local development for ascendstemacademy.com
# Run `make` or `make help` to see everything available.

SHELL := /bin/bash
COMPOSE := docker compose
WP_PORT ?= 8080

# Load .env if present so targets can echo the right URLs.
ifneq (,$(wildcard .env))
include .env
export
endif

.DEFAULT_GOAL := help
.PHONY: help up down destroy install reload logs shell wp db-export db-import adminer mail lint theme-watch status

help: ## Show this help
	@echo "Ascend STEM Academy — local development"
	@echo
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
	  | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'
	@echo

.env: ## Create .env from the example if it does not exist
	@test -f .env || (cp .env.example .env && echo "Created .env from .env.example")

up: .env ## Start the stack (first run also installs WordPress)
	$(COMPOSE) up -d db wordpress
	@echo "Waiting for WordPress to respond..."
	@for i in $$(seq 1 60); do \
	  curl -sf -o /dev/null "http://localhost:$(WP_PORT)" && break || sleep 2; \
	done
	@$(MAKE) --no-print-directory install
	@echo
	@echo "  Site:  http://localhost:$(WP_PORT)"
	@echo "  Admin: http://localhost:$(WP_PORT)/wp-admin"

install: ## Install/repair the WordPress install (idempotent)
	$(COMPOSE) run --rm \
	  -e WP_URL=http://localhost:$(WP_PORT) \
	  wpcli bash /scripts/install.sh

down: ## Stop the stack (database and uploads are preserved)
	$(COMPOSE) --profile cli --profile tools down

destroy: ## Stop the stack AND delete all local data (irreversible)
	@read -p "Delete the local database, uploads and plugins? [y/N] " ok; \
	  [ "$$ok" = "y" ] || { echo "Aborted."; exit 1; }
	$(COMPOSE) --profile cli --profile tools down -v

reload: ## Restart the web container (after changing php ini or wp-config)
	$(COMPOSE) restart wordpress

status: ## Show container status
	$(COMPOSE) ps

logs: ## Tail the WordPress and database logs
	$(COMPOSE) logs -f wordpress db

shell: ## Open a shell in the WordPress container
	$(COMPOSE) exec wordpress bash

wp: ## Run a wp-cli command, e.g. make wp CMD="plugin list"
	@test -n "$(CMD)" || { echo 'Usage: make wp CMD="plugin list"'; exit 1; }
	$(COMPOSE) run --rm wpcli wp --path=/var/www/html $(CMD)

adminer: ## Start the database browser on http://localhost:8081
	$(COMPOSE) --profile tools up -d adminer
	@echo "Adminer: http://localhost:$(ADMINER_PORT) (server: db)"

mail: ## Start Mailpit to catch outbound email on http://localhost:8025
	$(COMPOSE) --profile tools up -d mailpit
	@echo "Mailpit: http://localhost:$(MAILPIT_PORT)"

db-export: ## Dump the local database to db/local.sql
	@mkdir -p db
	$(COMPOSE) run --rm wpcli wp --path=/var/www/html db export /var/www/html/_dump.sql
	$(COMPOSE) cp wordpress:/var/www/html/_dump.sql db/local.sql
	$(COMPOSE) exec wordpress rm -f /var/www/html/_dump.sql
	@echo "Wrote db/local.sql"

db-import: ## Import db/local.sql into the local database
	@test -f db/local.sql || { echo "db/local.sql not found"; exit 1; }
	$(COMPOSE) cp db/local.sql wordpress:/var/www/html/_import.sql
	$(COMPOSE) run --rm wpcli wp --path=/var/www/html db import /var/www/html/_import.sql
	$(COMPOSE) exec wordpress rm -f /var/www/html/_import.sql

lint: ## Check theme PHP files for syntax errors
	@find wp-content -name '*.php' -print0 \
	  | xargs -0 -n1 -P4 php -l \
	  | (grep -v '^No syntax errors' || true)
	@echo "PHP lint complete."
