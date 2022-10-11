# This file is part of Bileto.
# Copyright (C) 2022 Probesys
# SPDX-License-Identifier: AGPL-3.0-or-later

.DEFAULT_GOAL := help

USER = $(shell id -u):$(shell id -g)

ifdef NO_DOCKER
	PHP = php
	COMPOSER = composer
	CONSOLE = php bin/console
else
	PHP = ./docker/bin/php
	COMPOSER = ./docker/bin/composer
	CONSOLE = ./docker/bin/console
endif

ifndef COVERAGE
	COVERAGE = --coverage-html ./coverage
endif

ifdef FILE
	PHPUNIT_FILE = $(FILE)
else
	PHPUNIT_FILE = ./tests
endif

ifdef FILTER
	PHPUNIT_FILTER = --filter=$(FILTER)
else
	PHPUNIT_FILTER =
endif

.PHONY: docker-start
docker-start: ## Start a development server with Docker
	@echo "Running webserver on http://localhost:8000"
	docker-compose -p bileto -f docker/docker-compose.yml up

.PHONY: docker-build
docker-build: ## Rebuild Docker containers
	docker-compose -p bileto -f docker/docker-compose.yml build

.PHONY: docker-clean
docker-clean: ## Clean the Docker stuff
	docker-compose -p bileto -f docker/docker-compose.yml down

.PHONY: install
install: ## Install the dependencies
	$(COMPOSER) install

.PHONY: db-setup
db-setup: ## Setup the database
	$(CONSOLE) doctrine:database:create
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

.PHONY: db-migrate
db-migrate: ## Migrate the database
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

.PHONY: db-reset
db-reset: ## Reset the database
ifndef FORCE
	$(error Please run the operation with FORCE=true)
endif
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create
	$(CONSOLE) doctrine:migrations:migrate --no-interaction
	$(CONSOLE) cache:clear

.PHONY: migration
migration: ## Generate a database migration from entities changes
	$(CONSOLE) make:migration

.PHONY: test
test: ## Run the test suite
	$(PHP) ./bin/phpunit \
		-c .phpunit.xml.dist \
		$(COVERAGE) --whitelist ./src \
		--testdox \
		$(PHPUNIT_FILTER) \
		$(PHPUNIT_FILE)

.PHONY: lint
lint: ## Execute the linter (PHPStan and PHP_CodeSniffer)
	$(PHP) vendor/bin/phpstan analyse -c .phpstan.neon
	$(PHP) vendor/bin/phpcs

.PHONY: lint-fix
lint-fix: ## Fix the errors detected by the linters (PHP_CodeSniffer)
	$(PHP) vendor/bin/phpcbf

.PHONY: tree
tree:  ## Display the structure of the application
	tree -I 'vendor|var|coverage' --dirsfirst -CA

.PHONY: help
help:
	@grep -h -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'
