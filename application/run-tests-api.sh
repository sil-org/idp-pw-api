#!/usr/bin/env bash
set -x

# Install composer dev dependencies
cd /data
composer install --prefer-dist --no-interaction --optimize-autoloader --no-progress

mkdir -p /data/runtime/mail

# Remove any ca.pem file that may have been added by the interactive test container
rm --force /data/console/runtime/ca.pem

make-ssl-cert generate-default-snakeoil

# Start apache
apache2ctl start

# Run codeception tests
/data/vendor/bin/codecept run api
#/data/vendor/bin/codecept run api --debug
TESTRESULTS_API=$?

echo "Note: If there are unexpected errors, try 'make clean' or manually redo id-broker test migration."

if [[ "TESTRESULTS_API" -ne 0 ]]; then
    exit $TESTRESULTS_API
fi
