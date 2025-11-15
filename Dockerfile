FROM php:7.4-apache

# Install Redis extension
RUN pecl install redis-5.3.7 \
    && docker-php-ext-enable redis

# Set working directory
WORKDIR /var/www/html
