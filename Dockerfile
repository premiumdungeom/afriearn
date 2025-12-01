FROM php:8.2-apache

# Copy project files to Apache web root
COPY . /var/www/html/

# Make sure Apache can read files
RUN chown -R www-data:www-data /var/www/html

# Expose web port
EXPOSE 80
