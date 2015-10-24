FROM php:5.6-cli

RUN apt-get update && apt-get install -y git zlib1g-dev

RUN git clone https://github.com/derickr/xdebug.git /usr/src/php/ext/xdebug

RUN docker-php-ext-install zip xdebug

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer global require phpunit/phpunit

env PATH ~/.composer/vendor/bin:$PATH
