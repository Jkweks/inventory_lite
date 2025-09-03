FROM php:8.2-apache
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev git zip unzip libzip-dev && docker-php-ext-install pdo pdo_pgsql zip && rm -rf /var/lib/apt/lists/*
RUN a2enmod rewrite
RUN { echo 'date.timezone=UTC'; echo 'upload_max_filesize=16M'; echo 'post_max_size=16M'; } > /usr/local/etc/php/conf.d/app.ini
WORKDIR /var/www/html
