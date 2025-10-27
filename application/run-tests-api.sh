#!/usr/bin/env bash
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

make-ssl-cert generate-default-snakeoil

# Start apache
apache2ctl start

# Run codeception tests
/data/vendor/bin/codecept run api -d
TESTRESULTS_API=$?

echo "Note: If there are unexpected errors, try 'make clean' or manually redo id-broker test migration."

if [[ "TESTRESULTS_API" -ne 0 ]]; then
    exit $TESTRESULTS_API
fi
