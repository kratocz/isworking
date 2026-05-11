FROM php:8.4-apache

# Install Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Deny HTTP access to dotfiles (.git/, .env*, ...), docs (*.md) and config (*.yml, Dockerfile)
RUN { \
        echo '<DirectoryMatch "/\..+">'; \
        echo '    Require all denied'; \
        echo '</DirectoryMatch>'; \
        echo '<FilesMatch "^\.">'; \
        echo '    Require all denied'; \
        echo '</FilesMatch>'; \
        echo '<FilesMatch "\.(md|ya?ml)$|^Dockerfile$">'; \
        echo '    Require all denied'; \
        echo '</FilesMatch>'; \
    } > /etc/apache2/conf-available/deny-sensitive.conf \
    && a2enconf deny-sensitive

# Set working directory
WORKDIR /var/www/html
