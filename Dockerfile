# ─────────────────────────────────────────────────────────────────────────────
# Sistem Pemberkasan — image produksi (PHP 8.2 FPM + Nginx di container terpisah)
# Target: VPS 2 GB RAM / disk 200 GB
# ─────────────────────────────────────────────────────────────────────────────
FROM php:8.2-fpm-alpine

# Ekstensi yang dibutuhkan Laravel 11 + Livewire + MySQL + PDF handling
RUN apk add --no-cache \
        bash \
        git \
        curl \
        unzip \
        icu-dev \
        libzip-dev \
        libpng-dev \
        oniguruma-dev \
        mysql-client \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        bcmath \
        intl \
        zip \
        opcache \
        gd \
    && apk del $PHPIZE_DEPS

# Konfigurasi PHP untuk produksi + upload PDF 20MB
COPY deploy/php.ini /usr/local/etc/php/conf.d/99-app.ini

# Pool PHP-FPM: 3 worker, dibatasi agar muat di RAM 2 GB
COPY deploy/php-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-app.conf

# Composer (versi stabil terbaru)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Layer dependency dipisah supaya cache Docker terpakai ulang saat kode berubah
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

# Salin source aplikasi
COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/app/documents storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

# Entrypoint: tunggu DB, migrate, cache config/route/view, lalu start FPM
COPY deploy/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
