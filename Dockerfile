# Production Dockerfile for Render — Full PHP App (Frontend + Backend + API)
FROM php:8.4-apache

# Install PHP extensions
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libonig-dev \
    libssl-dev \
    zip unzip curl \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite mbstring \
    && a2enmod rewrite headers \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Apache configuration
ENV APACHE_DOCUMENT_ROOT=/var/www/html
RUN sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:10000>/' /etc/apache2/sites-available/000-default.conf

# Allow .htaccess overrides
RUN echo "<Directory /var/www/html/>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>" > /etc/apache2/conf-available/hms-override.conf \
    && a2enconf hms-override

# Render routes traffic to the port declared by the container.
ENV PORT=10000

# Copy app
COPY . /var/www/html/

WORKDIR /var/www/html
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/uploads /var/www/html/data \
    && chown www-data:www-data /var/www/html/uploads /var/www/html/data \
    && chmod 750 /var/www/html/uploads /var/www/html/data

EXPOSE 10000

CMD ["apache2-foreground"]
