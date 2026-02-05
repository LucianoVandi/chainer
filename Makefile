.PHONY: help install test coverage analyse format format-check

help:
	@printf "Targets:\n"
	@printf "  install   Install dependencies (composer install)\n"
	@printf "  test      Run tests\n"
	@printf "  coverage  Run tests with coverage\n"
	@printf "  analyse   Run PHPStan\n"
	@printf "  format    Run PHP-CS-Fixer\n"

install:
	docker-compose run --rm composer

test:
	docker-compose run --rm test

coverage:
	docker-compose run --rm test composer test-coverage

analyse:
	docker-compose run --rm test composer analyse

format:
	docker-compose run --rm test composer format

format-check:
	docker-compose run --rm test composer format-check
