# Pin the exact patch version once php:8.4.27-fpm is on Docker Hub (CVE-2026-109618/109619,
# see docs/DECISIONS.md, ADR-004). Until then, "make init" pulls the latest 8.4 build.
ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libicu-dev \
    librabbitmq-dev \
    libssl-dev \
    && docker-php-ext-install -j$(nproc) \
        intl \
        opcache \
    && pecl install mongodb amqp pcov \
    && docker-php-ext-enable mongodb amqp pcov \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.validate_timestamps=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "pcov.enabled=0" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini

COPY conf.d/security.ini /usr/local/etc/php/conf.d/zz-security.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN useradd -m -u 1000 app

WORKDIR /var/www/app

USER app
