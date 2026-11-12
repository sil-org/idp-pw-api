#!/usr/bin/env bash

# print script lines as they are executed
set -x

# exit if any line in the script fails
set -e

echo "starting idp-pw-api version $GITHUB_REF_NAME"

if [[ $APP_ENV == "dev" ]]; then
    export XDEBUG_CONFIG="remote_enable=1 remote_host="$REMOTE_DEBUG_IP
    apt-get update && apt-get install -y php${PHP_VERSION}-xdebug
fi

make-ssl-cert generate-default-snakeoil

# exec so Apache replaces this shell as PID 1 and receives the container's stop signal
if [[ $PARAMETER_STORE_PATH ]]; then
  exec config-shim -v --path $PARAMETER_STORE_PATH apache2-foreground
else
  exec apache2-foreground
fi
