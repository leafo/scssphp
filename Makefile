test:
	vendor/bin/phpunit --colors tests

phar:
	php -dphar.readonly=0 vendor/bin/box build -v
