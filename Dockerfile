# Production Dockerfile for Render Backend Service
FROM php:8.4-apache

# Install system dependencies & PHP extensions required for HMS
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libonig-dev \
    libssl-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite mbstring \
    && a2enmod rewrite headers

# Configure Apache Document Root & Permissions
ENV APACHE_DOCUMENT_ROOT /var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Configure directory overrides
RUN echo "<Directory /var/www/html/>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>" > /etc/apache2/conf-available/hms-override.conf \
    && a2enconf hms-override

# Copy application source code
COPY . /var/www/html/

# Set working directory & permissions
WORKDIR /var/www/html
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
