# Render runs this to build and start the site (PHP 8.2 + Apache).
FROM php:8.2-apache

# PHP extensions the app needs: MySQL (pdo_mysql), photos (gd, exif). cURL is already included.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpng-dev libjpeg-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql gd exif \
    && rm -rf /var/lib/apt/lists/*

# Serve only the public folder, allow .htaccess, and listen on the port Render gives us.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
ENV PORT=10000
RUN a2enmod rewrite \
    && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    && sed -ri 's!Listen 80!Listen ${PORT}!g' /etc/apache2/ports.conf \
    && sed -ri 's!<VirtualHost \*:80>!<VirtualHost *:${PORT}>!g' /etc/apache2/sites-available/000-default.conf \
    && echo "upload_max_filesize=3M\npost_max_size=4M\ndate.timezone=Africa/Lagos" > /usr/local/etc/php/conf.d/app.ini

COPY . /var/www/html/

RUN mkdir -p storage/logs storage/mail \
    && chown -R www-data:www-data storage

EXPOSE 10000
