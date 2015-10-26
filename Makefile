tests:
	vendor/bin/phpunit --colors tests

compat:
	TEST_SCSS_COMPAT=1 vendor/bin/phpunit --colors tests | tail -2

standard:
	vendor/bin/phpcs --standard=PSR2 bin src tests *.php

phar:
	php -dphar.readonly=0 vendor/bin/box build -v
