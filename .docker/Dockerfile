# Use the PHP CLI with Alpine image
FROM php:8.5-cli-alpine3.23

ARG COMPOSER_ALLOW_SUPERUSER=1

# Install minimal system dependencies for Composer and dev tooling
RUN apk add --no-cache \
        curl \
        git \
        unzip

# Install PCOV for code coverage
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del .build-deps

RUN echo "pcov.enabled=1" > /usr/local/etc/php/conf.d/99-pcov.ini

# Use the default production configuration
#RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Set working directory
WORKDIR /var/www/

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
