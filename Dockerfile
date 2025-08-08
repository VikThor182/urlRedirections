FROM php:8.2-apache

# Installer les extensions PHP nécessaires
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install zip gd \
    && rm -rf /var/lib/apt/lists/*

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copier composer.json seulement (pas le lock qui peut ne pas exister)
COPY composer.json /var/www/html/

# Installer les dépendances PHP
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copier tous les fichiers de l'application
COPY . /var/www/html/

# Créer le dossier uploads et définir les permissions
RUN mkdir -p /var/www/html/uploads

# Définir les permissions pour Apache
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/uploads

# Créer un script d'initialisation pour les permissions
RUN echo '#!/bin/bash\n\
mkdir -p /var/www/html/uploads\n\
chown -R www-data:www-data /var/www/html/uploads\n\
chmod -R 777 /var/www/html/uploads\n\
exec "$@"' > /entrypoint.sh && chmod +x /entrypoint.sh

# Exposer le port 80
EXPOSE 80

# Configuration Apache pour permettre les uploads de fichiers
RUN echo "upload_max_filesize = 50M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini

# Utiliser le script d'entrypoint
ENTRYPOINT ["/entrypoint.sh"]

# Démarrer Apache
CMD ["apache2-foreground"]
