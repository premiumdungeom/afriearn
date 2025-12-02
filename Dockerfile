# Use official PHP with Apache
FROM php:8.2-apache

# Install system dependencies and enable Apache modules
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-install zip pdo pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable required Apache modules
RUN a2enmod rewrite headers && \
    echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Copy application files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port 10000
EXPOSE 10000

# Start Apache
CMD ["apache2-foreground"]RUN sed -i "s/80/${APACHE_PORT}/" /etc/apache2/ports.conf && \
    sed -i "s/80/${APACHE_PORT}/" /etc/apache2/sites-available/*.conf

# Start Apache
CMD ["apache2-foreground"]
