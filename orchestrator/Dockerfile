FROM php:8.4-cli-bookworm

ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl git libpq-dev unzip \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock symfony.lock ./
RUN composer install \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --no-scripts

COPY . .

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=5 \
  CMD php -r '$socket = @fsockopen("127.0.0.1", 8000); if (!$socket) { exit(1); } fclose($socket);'

CMD ["sh", "-lc", "if [ ! -f vendor/autoload.php ]; then composer install --prefer-dist --no-interaction --no-progress --no-scripts; fi; php -S 0.0.0.0:8000 -t public"]
