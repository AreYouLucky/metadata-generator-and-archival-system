# Laravel runtime for local development: web (artisan serve), horizon, reverb, scheduler.
FROM php:8.5-cli

COPY --from=ghcr.io/mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

# Composer runs on the host; the container only needs the PHP extensions.
RUN install-php-extensions pdo_pgsql redis pcntl intl zip bcmath exif sockets opcache

# Same UID as the ai-service image so both can write to the shared media volume.
ARG UID=1000
ARG GID=1000
RUN groupadd --gid "${GID}" app \
    && useradd --uid "${UID}" --gid app --create-home app \
    && mkdir -p /data/storage \
    && chown app:app /data/storage

WORKDIR /var/www/html
USER app

EXPOSE 8000 8080
