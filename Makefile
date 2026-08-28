-include local/Makefile

DOCKER_PHP_IMAGE ?= php:8.5-cli-alpine
DOCKER_COMPOSER_IMAGE ?= composer:2
DOCKER_RUN ?= docker run --rm -v $(PWD):/app -w /app
PHP ?= $(DOCKER_RUN) $(DOCKER_PHP_IMAGE) php
COMPOSER ?= $(DOCKER_RUN) $(DOCKER_COMPOSER_IMAGE) composer

PHPCS_ARGS ?= --standard=phpcs.xml --extensions=php --encoding=utf-8 -sp src tests

.PHONY: all deps qa phpstan cs csf phpunit

all: deps qa

deps:
	$(COMPOSER) update --no-interaction

qa: phpstan cs phpunit

phpstan:
	$(PHP) vendor/bin/phpstan analyse --configuration=phpstan.neon --memory-limit=512M

cs:
	$(PHP) vendor/bin/phpcs $(PHPCS_ARGS)

csf:
	$(PHP) vendor/bin/phpcbf $(PHPCS_ARGS)

phpunit:
	$(PHP) vendor/bin/phpunit --configuration=phpunit.xml
