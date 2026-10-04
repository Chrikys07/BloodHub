FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libfreetype6-dev libjpeg62-turbo-dev libonig-dev libpng-dev libwebp-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring zip gd \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY . /var/www/html
RUN mkdir -p storage/reports storage/sessions public/uploads/avatars \
    && chown -R www-data:www-data storage public/uploads \
    && chmod -R 0770 storage public/uploads

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php -r '$s=@fsockopen("127.0.0.1",80,$e,$m,3);if(!$s)exit(1);fwrite($s,"GET /login HTTP/1.0\r\nHost: localhost\r\n\r\n");$r=fgets($s);fclose($s);exit(str_contains((string)$r,"200")?0:1);'
