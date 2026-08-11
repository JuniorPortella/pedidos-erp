FROM php:8.2-apache-bookworm

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && printf 'ServerName localhost\n' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

COPY --from=composer:2.10 /usr/bin/composer /usr/local/bin/composer

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

COPY . .
