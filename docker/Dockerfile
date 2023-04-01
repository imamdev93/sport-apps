FROM alpine:edge

LABEL Maintainer="Imam Fahmi Fadillah <imam.dev93@gmail.com>" \
    Description="Laravel Octane on Alpine Linux."

ARG PHP_VERSION="8.2.3-r0"

# Install packages
RUN apk --no-cache add php82 php82-opcache php82-openssl php82-curl php82-phar php82-session \
    php82-fileinfo php82-pdo php82-pdo_mysql php82-mbstring php82-dom php82-xml php82-xmlwriter php82-sodium \
    php82-tokenizer php82-posix curl

# Symlink php82 => php
RUN ln -sf /usr/bin/php82 /usr/bin/php

# Configure PHP
COPY docker/php/custom.ini /etc/php82/conf.d/custom.ini

# Setup document root
RUN mkdir -p /var/www/html

# Make sure files/folders needed by the processes are accessable when they run under the nobody user
RUN chown -R nobody.nobody /var/www/html && \
    chown -R nobody.nobody /run

# Switch to use a non-root user from here on
USER nobody

# Add application
WORKDIR /var/www/html
COPY --chown=nobody src/ /var/www/html

# Install composer from the official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Run composer install to install the dependencies
RUN composer install --no-cache --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress

# Expose the port app is reachable on
CMD php artisan serve --host=0.0.0.0 --port=80
EXPOSE 80