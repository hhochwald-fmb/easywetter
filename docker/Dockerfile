# Lokale Entwicklungsumgebung – entspricht einem typischen Webhosting (Apache + PHP + .htaccess)
FROM php:8.3-apache
RUN apt-get update \
 && apt-get install -y --no-install-recommends libzip-dev unzip \
 && docker-php-ext-install pdo_mysql zip \
 && a2enmod rewrite headers \
 && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
 && rm -rf /var/lib/apt/lists/*
RUN { echo 'upload_max_filesize=40M'; echo 'post_max_size=42M'; echo 'expose_php=Off'; } > /usr/local/etc/php/conf.d/easywetter.ini
