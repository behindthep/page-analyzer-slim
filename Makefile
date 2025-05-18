PORT ?= 8000
start:
	php -S 0.0.0.0:$(PORT) -t public

setup:
	composer install
	cp -n .env.example .env

validate:
	composer validate

lint:
	composer exec --verbose phpcs -- public src
	composer exec --verbose phpstan

lint-fix:
	composer exec --verbose phpcbf -- public src 
