install:
	composer install

# -t меняет корневую директорую — место поиска index.php. помещать в нее только то что открыть напрямую из браузера
startDev:
	php -S localhost:8080 -t public public/index.php

PORT ?= 8000
# запуск приложения на проде
start:
	php -S 0.0.0.0:$(PORT) -t public public/index.php

console:
	composer exec --verbose psysh

lint:
	composer exec --verbose phpcs -- src tests
	composer exec --verbose phpstan

lint-fix:
	composer exec --verbose phpcbf -- src tests

test:
	composer exec --verbose phpunit tests

test-coverage:
	XDEBUG_MODE=coverage composer exec --verbose phpunit tests -- --coverage-clover=build/logs/clover.xml

test-coverage-text:
	XDEBUG_MODE=coverage composer exec --verbose phpunit tests -- --coverage-text
