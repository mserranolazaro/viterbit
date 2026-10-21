# Exact patch version, never a floating tag: 8.4.27 fixes CVE-2026-109618 and CVE-2026-109619
# (php2shell, see docs/DECISIONS.md, ADR-004). Bump it with each PHP security release.
ARG PHP_VERSION=8.4.27
FROM php:${PHP_VERSION}-fpm

# Extensions pinned to exact versions and built from their official GitHub releases instead of
# PECL: reproducible builds, and no dependency on pecl.php.net (it returned 504s on 21 Oct 2026).
ARG MONGODB_EXT_VERSION=2.5.5
ARG AMQP_EXT_VERSION=2.2.0
ARG PCOV_EXT_VERSION=1.0.12

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libicu-dev \
    librabbitmq-dev \
    libssl-dev \
    libzip-dev \
    && docker-php-ext-install -j$(nproc) \
        intl \
        opcache \
        zip \
    && build_ext() { \
        mkdir -p "/tmp/$1" && curl -fsSL "$2" | tar xz -C "/tmp/$1" --strip-components=1 \
        && cd "/tmp/$1" && phpize && ./configure --quiet && make -j"$(nproc)" >/dev/null && make install \
        && cd / && rm -rf "/tmp/$1"; \
    } \
    && build_ext mongodb "https://github.com/mongodb/mongo-php-driver/releases/download/${MONGODB_EXT_VERSION}/mongodb-${MONGODB_EXT_VERSION}.tgz" \
    && build_ext amqp "https://github.com/php-amqp/php-amqp/archive/refs/tags/v${AMQP_EXT_VERSION}.tar.gz" \
    && build_ext pcov "https://github.com/krakjoe/pcov/archive/refs/tags/v${PCOV_EXT_VERSION}.tar.gz" \
    && docker-php-ext-enable mongodb amqp pcov \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.validate_timestamps=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "pcov.enabled=0" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini

COPY conf.d/security.ini /usr/local/etc/php/conf.d/zz-security.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Same uid/gid as the host user (passed by make), so files written in the bind mount stay editable.
ARG UID=1000
ARG GID=1000
RUN groupadd -o -g "${GID}" app && useradd -o -m -u "${UID}" -g "${GID}" app

WORKDIR /var/www/app

USER app
