#!/usr/bin/env bash

# break on first error
set -e

# print script lines to stdout
set -x

# Install composer dev dependencies
cd /data
composer install --prefer-dist --no-interaction --optimize-autoloader --no-progress

mkdir -p /data/runtime/mail

if [[ -n "$SSL_CA_BASE64" ]]; then
    # Decode the base64 and write to the file
    caFile="/data/console/runtime/ca.pem"
    echo "$SSL_CA_BASE64" | base64 -d > "$caFile"
    if [[ $? -ne 0 || ! -s "$caFile" ]]; then
        echo "Failed to write database SSL certificate file: $caFile" >&2
        exit 1
    fi
fi

# Run database migrations
/data/yii migrate --interactive=0

# Install and enable xdebug for code coverage
apt-get update && apt-get install -y php-xdebug

# Run codeception tests
./vendor/bin/codecept run --fail-fast 1 unit

# Run local behat tests
./vendor/bin/behat --config=tests/features/behat.yml --strict --profile=local
