# Use multi-stage build for better layer caching and smaller final image
FROM php:8.3-fpm-bullseye AS base

# Build arguments
ARG TARGET_ENV=dev

# Environment variables
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    DRUPAL_VERSION=11.1 \
    PATH=${PATH}:/var/www/html/bin

USER root    
# Install system dependencies and PHP extensions in optimized layers
RUN set -eux && \
    apt-get update && \
    apt-get install -y --no-install-recommends \
        # Essential tools
        git \
        patch \
        wget \
        unzip \
        # Image processing
        libpng-dev \
        libjpeg-dev \
        libwebp-dev \
        libfreetype6-dev \
        # Database
        libpq-dev \
        default-mysql-client \
        # Other libraries
        libzip-dev \
        libxslt-dev \
        libbz2-dev \
        libfontconfig1 \
        libxrender1 \
        lsof && \
    # Configure GD with all image formats
    docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp && \
    # Install PHP extensions in parallel
    docker-php-ext-install -j$(nproc) \
        gd \
        opcache \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        zip \
        xsl \
        bz2 \
        exif \
        bcmath \
        intl && \
    # Install PECL extensions with fallback versions
    (pecl install apcu || pecl install apcu-5.1.23 || pecl install apcu-5.1.22) && \
    docker-php-ext-enable apcu && \
    # Clean up in same layer
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/pear

# Install Composer in separate layer for better caching
RUN  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer --2 \
     && chmod +x /usr/local/bin/composer 

# Development stage
FROM base AS development
RUN pecl install xdebug && \
    docker-php-ext-enable xdebug

# Production stage  
FROM base AS production
# Production-specific optimizations can go here

# Final stage - choose based on TARGET_ENV
FROM ${TARGET_ENV:-production} AS final

# Set working directory
WORKDIR /var/www/html

# Copy configuration files
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/php.ini /usr/local/etc/php/php.ini

# Copy development config if needed
COPY docker/php/php_dev.ini /tmp/php_dev.ini
RUN if [ "$TARGET_ENV" = "dev" ]; then \
        cp /tmp/php_dev.ini /usr/local/etc/php/php.ini; \
    fi && \
    rm -f /tmp/php_dev.ini

# Create directories with proper permissions BEFORE switching users
RUN mkdir -p \
        /var/www/html/web/sites/default \
        /var/www/html/web/modules \
        /var/www/html/web/themes \
        /var/www/html/recipes \
        /var/www/html/vendor \
        /var/www/html/bin && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html


RUN  git config --global --add safe.directory /var/www/html

# Switch to non-root user early
USER www-data

# Copy composer files for dependency caching
COPY --chown=www-data:www-data composer.json composer.lock ./

# Install Composer dependencies as www-data user
RUN set -eux && \
    # Create writable directories for composer
    mkdir -p $COMPOSER_HOME $COMPOSER_CACHE_DIR && \
    # Install dependencies
    composer install \
        $([ "$TARGET_ENV" = "prd" ] && echo "--no-dev") \
        --no-interaction \
        --no-progress \
        --optimize-autoloader \
        --apcu-autoloader && \
    # Clean up caches
    composer clear-cache || true && \
    rm -rf $COMPOSER_HOME $COMPOSER_CACHE_DIR || true

# Copy application code
COPY --chown=www-data:www-data . .

# Set executable permissions for scripts (as www-data user)
RUN find bin -type f -name "*" -exec chmod +x {} \; 2>/dev/null || true && \
    find vendor/drush/drush -name "drush" -exec chmod +x {} \; 2>/dev/null || true

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD php-fpm -t || exit 1

# Default command
CMD ["php-fpm"]