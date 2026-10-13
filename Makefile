PORT ?= 8000

start:
	PHP_CLI_SERVER_WORKERS=5 php -S 0.0.0.0:$(PORT) -t public

setup: install build-css

install:
	composer install
	npm ci

build-css:
	npm run build:css

lint:
	./vendor/bin/phpcs

db-load:
	psql -a -d $(DATABASE_URL) -f database.sql

lint-fix:
	./vendor/bin/phpcbf

test:
	./vendor/bin/phpunit