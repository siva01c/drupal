FROM php:8.3-fpm-bullseye
ARG TARGET_ENV=dev

# Copy configuration files
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/php.ini /usr/local/etc/php/php.ini

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better layer caching
COPY composer.json composer.lock ./

# Install dependencies and PHP extensions in a single RUN to reduce layers
USER root
RUN set -eux \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        apt-utils \
        libpng-dev \
        libjpeg-dev \
        libwebp-dev \
        libpq-dev \
        default-mysql-client \
        patch \
        wget \
        libzip-dev \
        libfontconfig \
        libxslt-dev \
        lsof \
        git \
        libbz2-dev \
        libxrender1 \
    # Configure and install PHP extensions
    && docker-php-ext-configure gd --with-jpeg=/usr \
    && docker-php-ext-install -j$(nproc) \
        gd \
        opcache \
        pdo \
        pdo_mysql \
        zip \
        xsl \
        bz2 \
        exif \
    # Install PECL extensions
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    # Conditionally install Xdebug for non-production environments
    && if [ "$TARGET_ENV" != "prd" ]; then \
        pecl install xdebug; \
        docker-php-ext-enable xdebug; \
       fi \
    # Install Composer
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer --2 \
    && chmod +x /usr/local/bin/composer \
    # Clean up apt cache
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* \
    && chown www-data:www-data /var/www

# Set Composer environment variables
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV DRUPAL_VERSION=11.1.7

# Install dependencies with Composer
RUN set -eux \
    && export COMPOSER_HOME="$(mktemp -d)" \
    && composer install --no-interaction \
    && composer check-platform-reqs \
    && rm -rf "$COMPOSER_HOME" \
    # Set proper permissions
    && chown -R www-data:www-data web/sites web/modules web/themes recipes \
    && chmod 755 /var/www \
    && chmod +x -R /var/www/html/bin \
    && chmod +x -R /var/www/html/vendor/drush/drush/drush

# Update PATH
ENV PATH=${PATH}:/var/www/html/bin

# Switch to non-root user
USER www-data