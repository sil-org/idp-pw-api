#!/usr/bin/env bash

# print script lines as they are executed
set -x

# exit if any line in the script fails
set -e

# establish a signal handler to catch the SIGTERM from a 'docker stop'
# reference: https://medium.com/@gchudnov/trapping-signals-in-docker-containers-7a57fdda7d86
term_handler() {
  apache2ctl stop
  exit 143; # 128 + 15 -- SIGTERM
}
trap 'kill ${!}; term_handler' SIGTERM

echo "starting idp-pw-api version $GITHUB_REF_NAME"

if [[ $APP_ENV == "dev" ]]; then
    export XDEBUG_CONFIG="remote_enable=1 remote_host="$REMOTE_DEBUG_IP
    apt-get update && apt-get install -y php-xdebug
fi

make-ssl-cert generate-default-snakeoil

if [[ $PARAMETER_STORE_PATH ]]; then
  config-shim -v --path $PARAMETER_STORE_PATH apache2ctl -k start -D FOREGROUND
else
  apache2ctl -k start -D FOREGROUND
fi

# endless loop with a wait is needed for the trap to work
while true
do
  tail -f /dev/null & wait ${!}
done
