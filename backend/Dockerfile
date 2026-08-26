FROM php:8.2-cli

# System dependencies required to build PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        unzip \
        git \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions required by the project
RUN docker-php-ext-install -j"$(nproc)" \
        pdo \
        pdo_pgsql \
        intl \
        opcache \
        zip

# Opcache sane defaults for local dev (kept minimal, no APCu/JIT tuning here)
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=1'; \
        echo 'opcache.validate_timestamps=1'; \
    } > /usr/local/etc/php/conf.d/opcache-freecrm.ini

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]
