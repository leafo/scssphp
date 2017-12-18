test:
	vendor/bin/phpunit --colors tests

compat:
	TEST_SCSS_COMPAT=1 vendor/bin/phpunit --colors tests | tail -2

standard:
	vendor/bin/phpcs --standard=PSR2 bin src example tests *.php
