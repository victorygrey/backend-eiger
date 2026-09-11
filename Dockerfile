FROM node:22-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.2-fpm-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends nginx supervisor curl unzip sqlite3 libsqlite3-dev libonig-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev util-linux \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_sqlite mbstring bcmath zip opcache gd exif pcntl \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
COPY --from=frontend /app/public/build ./public/build
RUN mkdir -p bootstrap/cache storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs \
    && composer dump-autoload --optimize --no-dev --no-interaction
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p /var/log/supervisor /data \
    && chmod -R a+rX app bootstrap config database public resources routes storage artisan composer.json composer.lock && chown -R www-data:www-data storage bootstrap/cache
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 CMD curl -fsS http://127.0.0.1/up || exit 1
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
