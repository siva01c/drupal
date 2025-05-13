FROM php:8.3-fpm-bullseye

ARG TARGET_ENV=dev

WORKDIR /

COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/php.ini /usr/local/etc/php/php.ini

WORKDIR /var/www/html

COPY composer.json /var/www/html/composer.json
COPY composer.lock /var/www/html/composer.lock

USER root
RUN set -eux; \
    apt-get update && apt-get install -y \
    apt-utils libpng-dev libjpeg-dev libwebp-dev libpq-dev default-mysql-client \
    patch wget libzip-dev libfontconfig libxslt-dev lsof git libbz2-dev libxrender1 \
    && chown www-data:www-data /var/www

RUN set -eux; \
    docker-php-ext-configure gd --with-jpeg=/usr \
    && docker-php-ext-install gd opcache pdo pdo_mysql zip xsl bz2 exif

RUN set -eux; \ 
    pecl install apcu \
    && docker-php-ext-enable apcu

# Install Xdebug
RUN set -eux; \
    if [ "$TARGET_ENV" != "prd" ]; then \
      pecl install xdebug \
      && docker-php-ext-enable xdebug \
    ; fi

RUN set -eux; \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer --2 \
    && chmod +x /usr/local/bin/composer 

ENV COMPOSER_ALLOW_SUPERUSER 1  

ENV DRUPAL_VERSION 11.1.7

RUN set -eux; \
	export COMPOSER_HOME="$(mktemp -d)"; \
	composer install --no-interaction; \
	composer check-platform-reqs; \
    rm -rf "$COMPOSER_HOME"

# Clean up
RUN rm -rf /var/lib/apt/lists/* /tmp/* ; \
    chown -R www-data:www-data web/sites web/modules web/themes recipes; \
    chmod 755 /var/www; \
    chmod +x -R /var/www/html/vendor/bin; \
    chmod +x -R /var/www/html/bin; \
    chmod +x -R /var/www/html/vendor/drush/drush/drush 
 
    
ENV PATH=${PATH}:/var/www/html/bin

USER www-data