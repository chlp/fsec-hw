FROM php:7.3-cli
COPY . /usr/src/app
WORKDIR /usr/src/app/php

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN apt-get clean
RUN apt-get update
RUN apt-get install -y git zip
RUN composer install

CMD [ "php", "./telemetryCollector.php" ]
