.DEFAULT_GOAL := help

USER = $(shell id -u):$(shell id -g)

ifdef NO_DOCKER
	PHP = php
	COMPOSER = composer
else
	PHP = ./docker/bin/php
	COMPOSER = ./docker/bin/composer
endif

.PHONY: docker-start
docker-start: ## Start a development server with Docker
	@echo "Running webserver on http://localhost:8000"
	docker-compose -p probesuite -f docker/docker-compose.yml up

.PHONY: docker-build
docker-build: ## Rebuild Docker containers
	docker-compose -p probesuite -f docker/docker-compose.yml build

.PHONY: docker-clean
docker-clean: ## Clean the Docker stuff
	docker-compose -p probesuite -f docker/docker-compose.yml down

.PHONY: install
install: ## Install the dependencies
	$(COMPOSER) install

.PHONY: lint
lint: ## Execute the linter (PHPStan and PHP_CodeSniffer)
	$(PHP) vendor/bin/phpstan analyse -c .phpstan.neon
	$(PHP) vendor/bin/phpcs

.PHONY: lint-fix
lint-fix: ## Fix the errors detected by the linters (PHP_CodeSniffer)
	$(PHP) vendor/bin/phpcbf

.PHONY: help
help:
	@grep -h -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'
