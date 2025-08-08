FROM php:8.2-apache

# Installer les extensions PHP nécessaires
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copier les fichiers de configuration d'abord
COPY composer.json composer.lock /var/www/html/

# Installer les dépendances PHP
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader

# Copier les fichiers de l'application
COPY . /var/www/html/

# Créer le dossier uploads avec les bonnes permissions AVANT de changer le propriétaire
RUN mkdir -p /var/www/html/uploads \
    && chmod 777 /var/www/html/uploads

# Définir les permissions pour tous les fichiers
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# S'assurer que uploads reste accessible en écriture
RUN chmod 777 /var/www/html/uploads

# Exposer le port 80
EXPOSE 80

# Configuration Apache pour permettre les uploads de fichiers
RUN echo "upload_max_filesize = 50M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini

# Démarrer Apache
CMD ["apache2-foreground"]
