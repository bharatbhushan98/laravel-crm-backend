# Use PHP 8.2 with Apache
FROM php:8.2-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libonig-dev zip \
    && docker-php-ext-install pdo pdo_mysql zip

# Enable Apache rewrite module (important for Laravel routes)
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Copy composer from official composer image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install PHP dependencies (ignore platform requirements)
RUN composer install --no-interaction --no-dev --optimize-autoloader --ignore-platform-reqs

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache

# 👉 Point Apache to Laravel’s public directory
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

# 👉 Update Apache configuration file accordingly
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf

# Expose port 80
EXPOSE 80

# Start Apache server
CMD ["apache2-foreground"]