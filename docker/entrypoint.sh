#!/bin/sh
set -eu
cd /var/www/html
: "${APP_KEY:?APP_KEY must be configured and retained across updates}"
: "${DB_DATABASE:=/data/database.sqlite}"
export DB_DATABASE
export RUN_QUEUE="${RUN_QUEUE:-true}"
export RUN_SCHEDULER="${RUN_SCHEDULER:-true}"
umask 027
mkdir -p /data/backups storage/app/public storage/app/pim-media storage/app/pim-drop storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
if [ "${DB_CONNECTION:-sqlite}" = sqlite ]; then
    case "$DB_DATABASE" in /data/*) ;; *) echo 'SQLite DB_DATABASE must be under /data' >&2; exit 1 ;; esac
    if [ -s "$DB_DATABASE" ]; then
        backup="/data/backups/pre-migrate-$(date -u +%Y%m%dT%H%M%S)-$$.sqlite"
        sqlite3 "$DB_DATABASE" ".backup '$backup'"
        test "$(sqlite3 "$backup" 'PRAGMA integrity_check;')" = ok
    else
        touch "$DB_DATABASE"
    fi
fi
chown -R www-data:www-data /data storage bootstrap/cache
chmod -R 775 /data storage bootstrap/cache
php artisan config:clear
runuser -u www-data -- php artisan migrate --force
runuser -u www-data -- php artisan db:seed --class=UserSeeder --force
runuser -u www-data -- php artisan db:seed --class=AtomMasterDataSeeder --force
rm -f public/storage && ln -s /var/www/html/storage/app/public public/storage
runuser -u www-data -- php artisan config:cache
runuser -u www-data -- php artisan route:cache
runuser -u www-data -- php artisan view:cache
nginx -t
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
