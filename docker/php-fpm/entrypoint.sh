#!/bin/sh
set -e
service cron start
service nginx start
exec "$@"
