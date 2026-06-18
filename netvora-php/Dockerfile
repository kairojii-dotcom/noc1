# =====================================================================
# NETVORA NOC — PHP 8.3-FPM image (Coolify / Docker ready)
# =====================================================================
FROM php:8.3-fpm-alpine

# System deps + PHP extensions (pgsql for Supabase, snmp for OLT monitoring)
RUN apk add --no-cache \
        postgresql-dev \
        net-snmp-dev \
        net-snmp-tools \
        oniguruma-dev \
        libxml2-dev \
        curl \
        bash \
        $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring simplexml \
    && pecl install snmp 2>/dev/null || true \
    && docker-php-ext-enable snmp 2>/dev/null || true \
    && apk del $PHPIZE_DEPS

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . /var/www

RUN composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || true \
    && mkdir -p /var/www/storage && chown -R www-data:www-data /var/www



EXPOSE 9000
CMD ["php-fpm"]
