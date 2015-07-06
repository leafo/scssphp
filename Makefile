test:
	vendor/bin/phpunit --colors tests

phar:
	vendor/bin/box build -v
