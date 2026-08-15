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
ENV APACHE_DOCUMENT_ROOT /var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Allow .htaccess overrides
RUN echo "<Directory /var/www/html/>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>" > /etc/apache2/conf-available/hms-override.conf \
    && a2enconf hms-override

# Render uses port 10000 by default
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf
ENV PORT=10000

# Copy app
COPY . /var/www/html/

WORKDIR /var/www/html
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/uploads && chmod 777 /var/www/html/uploads

EXPOSE 10000

CMD ["apache2-foreground"]
