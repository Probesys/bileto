# This file is part of Bileto.
# Copyright 2022-2024 Probesys
# SPDX-License-Identifier: AGPL-3.0-or-later

.DEFAULT_GOAL := help

USER = $(shell id -u):$(shell id -g)

DOCKER_COMPOSE = docker compose -f docker/development/docker-compose.yml

ifdef NO_DOCKER
	PHP = php
	COMPOSER = composer
	CONSOLE = php bin/console
	NPM = npm
else
	PHP = ./docker/bin/php
	COMPOSER = ./docker/bin/composer
	CONSOLE = ./docker/bin/console
	NPM = ./docker/bin/npm
endif

ifdef DATABASE
	DOCKER_COMPOSE_PROFILE = --profile $(DATABASE)
else
	DOCKER_COMPOSE_PROFILE = --profile pgsql
endif

.PHONY: docker-start
docker-start: PORT ?= 8000
docker-start: ## Start a development server with Docker (can take a PORT argument)
	@echo "Running webserver on http://localhost:$(PORT)"
	$(DOCKER_COMPOSE) $(DOCKER_COMPOSE_PROFILE) up

.PHONY: docker-build
docker-build: ## Rebuild Docker containers
	$(DOCKER_COMPOSE) build --pull

.PHONY: docker-pull
docker-pull: ## Pull the Docker images from the Docker Hub
	$(DOCKER_COMPOSE) pull --ignore-buildable

.PHONY: docker-clean
docker-clean: ## Clean the Docker stuff
	$(DOCKER_COMPOSE) --profile pgsql --profile mariadb down -v

.PHONY: docker-image
docker-image: ## Build the Docker image for production (take a VERSION argument)
ifndef VERSION
	$(error You need to provide a "VERSION" argument)
endif
	docker build \
		--pull \
		--build-arg VERSION="$(VERSION)" \
		--build-arg SOURCE_COMMIT="$(shell git describe --match '' --always --abbrev=42 --dirty)" \
		-t ghcr.io/probesys/bileto:$(VERSION) \
		-f docker/production/Dockerfile \
		.

.PHONY: install
install: INSTALLER ?= all
install: ## Install the dependencies (can take an INSTALLER argument)
ifeq ($(INSTALLER), $(filter $(INSTALLER), all composer))
	$(COMPOSER) install
endif
ifeq ($(INSTALLER), $(filter $(INSTALLER), all npm))
	$(NPM) install
endif

.PHONY: db-setup
db-setup: ## Setup the database
	$(CONSOLE) doctrine:database:create
	$(CONSOLE) doctrine:migrations:migrate --no-interaction
ifndef NO_SEED
	$(CONSOLE) db:seeds:load
endif

.PHONY: db-migrate
db-migrate: ## Migrate the database
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

.PHONY: db-rollback
db-rollback: ## Rollback the database to the previous version
	$(CONSOLE) doctrine:migrations:migrate --no-interaction prev

.PHONY: db-reset
db-reset: ## Reset the database
ifndef FORCE
	$(error Please run the operation with FORCE=true)
endif
ifndef NO_DOCKER
	$(DOCKER_COMPOSE) stop worker
endif
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create
	$(CONSOLE) doctrine:migrations:migrate --no-interaction
	$(CONSOLE) cache:clear
ifndef NO_SEED
	$(CONSOLE) db:seeds:load
endif
ifndef NO_DOCKER
	$(DOCKER_COMPOSE) start worker
endif

.PHONY: translations
translations: ## Update the translations from the code
	$(CONSOLE) translation:extract --format=yaml --force --clean en_GB
	$(CONSOLE) translation:extract --format=yaml --force --clean fr_FR
	# Restore these files as keys are removed from them whereas they should not.
	git restore translations/security+intl-icu.* translations/validators+intl-icu.*

.PHONY: migration
migration: ## Generate a database migration from entities changes
	$(CONSOLE) make:migration

.PHONY: icons
icons: ## Build the icons asset
	$(NPM) run build:icons

.PHONY: test
test: FILE ?= ./tests
ifdef FILTER
test: override FILTER := --filter=$(FILTER)
endif
test: COVERAGE ?= --coverage-html ./coverage
test: ## Run the test suite (can take FILE, FILTER and COVERAGE arguments)
	$(PHP) ./vendor/bin/phpunit \
		-c .phpunit.xml.dist \
		--testdox \
		$(COVERAGE) \
		$(PHPUNIT_FILTER) \
		$(PHPUNIT_FILE)

.PHONY: lint
lint: ## Execute the linter (PHPStan and PHP_CodeSniffer)
	$(PHP) vendor/bin/phpstan analyse --memory-limit 1G -v -c .phpstan.neon
	$(PHP) vendor/bin/rector process --dry-run --config .rector.php
	$(PHP) vendor/bin/phpcs
	$(CONSOLE) lint:container
	$(CONSOLE) lint:twig
	$(CONSOLE) lint:yaml translations/*
	$(NPM) run lint-js
	$(NPM) run lint-css

.PHONY: lint-fix
lint-fix: ## Fix the errors detected by the linters (PHP_CodeSniffer)
	$(PHP) vendor/bin/rector process --config .rector.php
	$(PHP) vendor/bin/phpcbf
	$(NPM) run lint-js-fix
	$(NPM) run lint-css-fix

.PHONY: release
release: ## Release a new version (take a VERSION argument)
ifndef VERSION
	$(error You need to provide a "VERSION" argument)
endif
	echo $(VERSION) > VERSION.txt
	rm -rf public/assets
	$(NPM) run build
	$(EDITOR) CHANGELOG.md
	git add .
	git commit -m "release: Publish version $(VERSION)"
	git tag -a $(VERSION) -m "Release version $(VERSION)"

.PHONY: tree
tree:  ## Display the structure of the application
	tree -I 'vendor|node_modules|var|coverage' --dirsfirst -CA

.PHONY: help
help:
	@grep -h -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'
