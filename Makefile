PORT ?= 8000

start:
	php -S 0.0.0.0:$(PORT) -t public

setup:
	composer install
	cp -n .env.example .env
	touch database/database.sqlite

console:
	composer exec --verbose psysh

lint:
	composer exec --verbose phpcs -- src public
	composer exec --verbose phpstan

lint-fix:
	composer exec --verbose phpcbf -- src public
