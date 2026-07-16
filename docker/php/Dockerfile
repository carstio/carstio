# syntax=docker/dockerfile:1.7
#
# PHP 8.4 FPM image used for both the `app` (FastCGI) and CLI workflows
# (`docker compose exec app ...`). Kept deliberately simple so contributors
# can read it top-to-bottom.
#
# Layer plan:
#   1. install system + PHP build dependencies once
#   2. install required PHP extensions
#   3. install Composer
#   4. add a non-root user that matches a typical host UID
#   5. set the working directory and a sane PHP runtime config

FROM php:8.4-fpm-bookworm

# Default UID/GID match the most common Linux host user. Override at build
# time with --build-arg if your host UID differs:
#   docker compose build --build-arg APP_UID=$(id -u) --build-arg APP_GID=$(id -g)
ARG APP_UID=1000
ARG APP_GID=1000

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    PHP_INI_MEMORY_LIMIT=512M

# System packages required for the PHP extensions and for the entrypoint.
# We use --mount=type=cache to keep the apt cache between builds.
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libonig-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libxml2-dev \
        zlib1g-dev \
    ; \
    rm -rf /var/lib/apt/lists/*

# Install PHP extensions required by Symfony, Doctrine and Dompdf.
# pdo_mysql is the database driver; intl/zip/opcache/mbstring/gd are needed
# for the framework, asset mapper, and PDF rendering.
RUN set -eux; \
    docker-php-ext-configure intl; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        intl \
        zip \
        opcache \
        mbstring \
        gd \
        exif

# Composer (latest stable).
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Production-friendly opcache + a generous upload limit for attachment uploads.
RUN { \
        echo 'opcache.memory_consumption=192'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=1'; \
        echo 'opcache.revalidate_freq=2'; \
        echo 'memory_limit=${PHP_INI_MEMORY_LIMIT}'; \
        echo 'upload_max_filesize=20M'; \
        echo 'post_max_size=22M'; \
    } > /usr/local/etc/php/conf.d/zz-app.ini

# Non-root user owning /var/www/html so bind-mounted source files and
# var/cache, var/log and var/storage stay writable.
RUN set -eux; \
    groupadd --gid "${APP_GID}" app || groupmod -n app "$(getent group ${APP_GID} | cut -d: -f1)"; \
    useradd --uid "${APP_UID}" --gid app --create-home --shell /bin/bash app || usermod -l app -d /home/app -m "$(getent passwd ${APP_UID} | cut -d: -f1)"

WORKDIR /var/www/html

# Pre-create the directories that get mounted as named volumes (vendor)
# or that the application writes to at runtime (var/, public/uploads), so
# they inherit app:app ownership the first time Docker attaches the
# volume. Without this the named app_vendor volume stays root-owned and
# `composer install` fails with EACCES.
RUN set -eux; \
    mkdir -p \
        /var/www/html/vendor \
        /var/www/html/var/cache \
        /var/www/html/var/log \
        /var/www/html/var/storage/attachments \
        /var/www/html/public/uploads/settings/company; \
    chown -R app:app /var/www/html

# Make sure FPM listens on TCP so the `web` service can reach it.
RUN sed -i 's|^listen = .*|listen = 9000|' /usr/local/etc/php-fpm.d/www.conf

USER app

EXPOSE 9000
CMD ["php-fpm", "-F"]
