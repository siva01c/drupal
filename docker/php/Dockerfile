FROM php:8.3-fpm-bullseye

WORKDIR /

COPY www.conf /usr/local/etc/php-fpm.d/www.conf
COPY php.ini /usr/local/etc/php/php.ini

WORKDIR /var/www/html

USER root
RUN apt-get update && apt-get install -y \
    apt-utils libpng-dev libjpeg-dev libwebp-dev libpq-dev default-mysql-client \
    patch wget libzip-dev libfontconfig libxslt-dev lsof git libbz2-dev libxrender1 \
    && chown www-data:www-data /var/www

RUN  docker-php-ext-configure gd --with-jpeg=/usr \
    && docker-php-ext-install gd opcache pdo pdo_mysql zip xsl bz2 exif

RUN pecl install apcu \
    && docker-php-ext-enable apcu

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer --2 \
    && chmod +x /usr/local/bin/composer

# Clean up
RUN rm -rf /var/lib/apt/lists/* /tmp/* \
    && chown -R www-data:www-data /var/www

USER www-data