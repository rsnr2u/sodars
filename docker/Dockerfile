# Stage 1: Build & Dependencies
FROM php:8.2-fpm-alpine AS builder

WORKDIR /var/www/html

# Install system builds dependencies
RUN apk add --no-cache \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libwebp-dev \
    icu-dev \
    sqlite-dev \
    postgresql-dev \
    oniguruma-dev \
    $PHPIZE_DEPS

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        gd \
        bcmath \
        intl \
        opcache \
        pdo \
        pdo_mysql \
        pdo_sqlite \
        zip \
        sockets

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer files and run install to leverage Docker layer caching
COPY composer.json composer.lock ./

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# Stage 2: Final Production Image
FROM php:8.2-fpm-alpine

WORKDIR /var/www/html

# Install required run-time libraries and supervisor
RUN apk add --no-cache \
    libzip \
    libpng \
    libjpeg-turbo \
    freetype \
    libwebp \
    icu-libs \
    sqlite-libs \
    supervisor \
    bash \
    shadow

# Copy PHP extensions built in the builder stage
COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=builder /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d

# Copy Composer binary
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application source
COPY . .

# Copy production vendor directory from builder
COPY --from=builder /var/www/html/vendor ./vendor

# Set permissions for Laravel directory structure
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Copy custom PHP & Opcache production configuration
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

EXPOSE 9000

CMD ["php-fpm"]
