FROM php:7.4-fpm

RUN apt-get update \
    && apt-get install -y libssl-dev \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb

RUN apt-get install -y zip unzip

RUN apt-get clean && rm -rf /var/lib/apt/lpecl install mongoists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY app/composer* /home/www-data/app/

RUN usermod -u 1000 -d /home/www-data www-data
RUN chown -R www-data:www-data /home/www-data

USER www-data

WORKDIR /home/www-data/app

RUN composer install

COPY app/* /home/www-data/app/

CMD [ "php" ,"-S", "0.0.0.0:8000", "-t", "/home/www-data/app" ]