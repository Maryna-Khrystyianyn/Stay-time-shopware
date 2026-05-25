FROM php:8.3-fpm

# Системні залежності
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libxml2-dev \
    libxslt-dev \
    libcurl4-openssl-dev \
    unzip \
    git \
    nginx \
    supervisor \
    curl \
    && rm -rf /var/lib/apt/lists/*

# PHP-розширення для Shopware 6
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        intl \
        pdo_mysql \
        gd \
        zip \
        opcache \
        xsl \
        curl \
        dom \
        xml

# Composer
COPY --from=composer:2 /usr/local/bin/composer /usr/local/bin/composer

# Node.js (для збірки storefront)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Робоча директорія
WORKDIR /var/www/html

# Копіюємо весь проєкт
COPY . .

# Встановлюємо залежності
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Запускаємо composer scripts після копіювання файлів
RUN composer run-script post-install-cmd --no-interaction || true

# PHP конфігурація
COPY docker/php.ini /usr/local/etc/php/conf.d/shopware.ini

# Nginx конфігурація
COPY docker/nginx.conf /etc/nginx/sites-available/default

# Supervisor для запуску Nginx + PHP-FPM разом
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Права доступу
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/var \
    && chmod -R 775 /var/www/html/public \
    && chmod -R 775 /var/www/html/config \
    && chmod -R 775 /var/www/html/files

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
