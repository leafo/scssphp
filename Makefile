tests:
	vendor/bin/phpunit --colors tests

standard:
	vendor/bin/phpcs --standard=PSR2 bin src tests *.php

phar:
	php -dphar.readonly=0 vendor/bin/box build -v
