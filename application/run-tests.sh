#!/usr/bin/env bash

# break on first error
set -e

# print script lines to stdout
set -x

# Install composer dev dependencies
cd /data
composer install --prefer-dist --no-interaction --optimize-autoloader --no-progress

mkdir -p /data/runtime/mail

# Remove any ca.pem file that may have been added by the interactive test container
rm --force /data/console/runtime/ca.pem

# Install and enable xdebug for code coverage
apt-get update && apt-get install -y php-xdebug

# Run codeception tests
./vendor/bin/codecept run --fail-fast 1 unit

# Run local behat tests
./vendor/bin/behat --config=tests/features/behat.yml --strict --profile=local
