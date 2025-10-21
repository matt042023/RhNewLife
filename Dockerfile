# Image officielle PHP 8.3 FPM basée sur Debian Bookworm
FROM php:8.3-fpm

# Installer les dépendances système et extensions PHP pour Symfony 7
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    && docker-php-ext-install -j$(nproc) \
        intl \
        opcache \
        pdo_mysql \
        zip \
        gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copier la configuration PHP pour Symfony
COPY docker/php/symfony.ini $PHP_INI_DIR/conf.d/symfony.ini
COPY docker/php/docker-php-ext-opcache.ini $PHP_INI_DIR/conf.d/docker-php-ext-opcache.ini

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Définir le répertoire de travail
WORKDIR /var/www/html

# Exposer le port PHP-FPM
EXPOSE 9000

# Démarrer PHP-FPM
CMD ["php-fpm"]
