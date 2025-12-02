# Use official PHP with Apache
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-install zip pdo pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite and set ServerName
RUN a2enmod rewrite && \
    echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Configure Apache DirectoryIndex
RUN echo "DirectoryIndex index.php index.html" > /etc/apache2/conf-available/directory-index.conf && \
    a2enconf directory-index

# Copy application files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port 10000 (Render uses dynamic ports)
EXPOSE 10000

# Use Render's port environment variable
ENV APACHE_PORT=10000

# Configure Apache to use Render's port
RUN sed -i "s/80/${APACHE_PORT}/" /etc/apache2/ports.conf && \
    sed -i "s/80/${APACHE_PORT}/" /etc/apache2/sites-available/*.conf

# Start Apache
CMD ["apache2-foreground"]
