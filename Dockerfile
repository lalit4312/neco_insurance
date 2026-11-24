FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && printf 'Listen 10000\n' > /etc/apache2/ports.conf \
    && printf '%s\n' \
        '<VirtualHost *:10000>' \
        '    DocumentRoot /var/www/html' \
        '    <Directory /var/www/html>' \
        '        AllowOverride All' \
        '        Require all granted' \
        '    </Directory>' \
        '    ErrorLog ${APACHE_LOG_DIR}/error.log' \
        '    CustomLog ${APACHE_LOG_DIR}/access.log combined' \
        '</VirtualHost>' \
        > /etc/apache2/sites-available/000-default.conf \
    && printf 'upload_max_filesize=6M\npost_max_size=8M\n' > /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html
COPY . .

RUN mkdir -p uploads \
    && chown -R www-data:www-data uploads

EXPOSE 10000

CMD ["sh", "-c", "chown www-data:www-data /var/www/html/uploads && exec apache2-foreground"]
