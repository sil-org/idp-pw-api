start: api

test: testlocal testintegration

testlocal: testunit testapi

testunit: composer broker
	docker compose run --rm unittest

testapi:
	docker compose kill broker
	docker compose up -d broker
	docker compose run --rm apitest

testintegration:
	docker compose run --rm integrationtest

api: broker composer
	docker compose up -d api zxcvbn phpmyadmin brokerpma emailpma

composer:
	docker compose run --rm cli composer install

composershow:
	docker compose run --rm cli bash -c 'composer show --format=json --no-dev --no-ansi --locked | jq "[.locked[] | { \"name\": .name, \"version\": .version }]" > dependencies.json'

composerupdate:
	docker compose run --rm cli bash -c "composer update"
	make composershow

broker:
	docker compose up -d broker

bounce:
	docker compose up -d api

clean:
	docker compose kill
	docker compose rm -f

psr2:
	docker compose run --rm cli bash -c "vendor/bin/php-cs-fixer fix ."
