
FROM node:24-bookworm-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT
ARG VITE_REVERB_SCHEME

ENV VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY}
ENV VITE_REVERB_HOST=${VITE_REVERB_HOST}
ENV VITE_REVERB_PORT=${VITE_REVERB_PORT}
ENV VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME}

RUN npm run build


FROM php:8.2-apache-bookworm

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    REVERB_PUBLISH_HOST=127.0.0.1 \
    REVERB_PUBLISH_PORT=8080 \
    REVERB_PUBLISH_SCHEME=http

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libwebp-dev \
        libzip-dev \
        supervisor \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        curl \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        zip \
    && printf 'upload_max_filesize=8M\npost_max_size=10M\nmemory_limit=256M\n' \
        > /usr/local/etc/php/conf.d/uploads.ini \
    && printf 'opcache.enable=1\nopcache.memory_consumption=96\nopcache.interned_strings_buffer=16\nopcache.max_accelerated_files=10000\nopcache.validate_timestamps=0\nrealpath_cache_size=4096K\nrealpath_cache_ttl=600\n' \
        > /usr/local/etc/php/conf.d/zz-performance.ini \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts \
    --no-autoloader

COPY . .

COPY --from=frontend /app/public/build ./public/build
COPY docker/apache/reverb-proxy.conf /etc/apache2/conf-available/reverb-proxy.conf
COPY docker/apache/mpm-tuning.conf /etc/apache2/conf-available/mpm-tuning.conf
COPY docker/apache/error-pages.conf /etc/apache2/conf-available/error-pages.conf
COPY docker/apache/perf-headers.conf /etc/apache2/conf-available/perf-headers.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/supervisor/essential-watchdog.py /usr/local/bin/essential-watchdog.py
COPY docker/supervisor/scheduler-loop.sh /usr/local/bin/scheduler-loop.sh
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint

RUN composer dump-autoload --no-dev --optimize --no-interaction \
    && composer check-platform-reqs --no-dev \
    && a2enmod headers rewrite proxy proxy_http proxy_wstunnel \
    && a2enconf reverb-proxy mpm-tuning error-pages perf-headers \
    && sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && chmod +x /usr/local/bin/docker-entrypoint \
    && sed -i 's/\r$//' /usr/local/bin/scheduler-loop.sh \
    && mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/docker-entrypoint"]

CMD ["supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
