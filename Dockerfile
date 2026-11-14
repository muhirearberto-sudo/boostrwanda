
# Use PHP with Apache
FROM php:8.2-apache

# Install PHP MySQL extensions and enable Apache rewriting
RUN docker-php-ext-install mysqli pdo_mysql \
    && a2enmod rewrite

# Allow .htaccess files to work
RUN printf '%s\n' \
    '<Directory /var/www/html>' \
    '    AllowOverride All' \
    '    Require all granted' \
    '</Directory>' \
    > /etc/apache2/conf-available/boostrwanda.conf \
    && a2enconf boostrwanda

# Set project directory
WORKDIR /var/www/html

# Copy BoostRwanda files into the container
COPY . /var/www/html

# Set file permissions
RUN chown -R www-data:www-data /var/www/html

# Apache port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
