#!/bin/sh
# Retention must keep running even when unrelated integration schedules are off.
while true; do
    php /var/www/html/artisan fit-photos:purge
    sleep 60
done
