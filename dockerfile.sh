#!/usr/bin/env bash

if [[ -z "$PHP_RUNTIME" ]]; then
    PHP_RUNTIME='php:7.4-cli'
fi

RUN_CMDS=''
if [[ $PHPUNIT_OPTS =~ (^|[[:space:]])--coverage-[[:alpha:]] ]]; then
    RUN_CMDS="$RUN_CMDS && \\\\\n    pecl install pcov && docker-php-ext-enable pcov"
fi

if [[ "1" != "$CHECK_CS" ]]; then
    COMPOSER_REMOVE='composer remove --dev --no-update friendsofphp/php-cs-fixer'
fi

echo -e "
FROM $PHP_RUNTIME

RUN apt-get update && apt-get install -y curl git unzip libgmp-dev libonig-dev && \\
    ln -s /usr/include/x86_64-linux-gnu/gmp.h /usr/include/gmp.h && \\
    echo memory_limit = 256M > \$(php -r 'echo PHP_CONFIG_FILE_SCAN_DIR;')/zz-custom.ini && \\
    docker-php-ext-install mbstring gmp${RUN_CMDS}

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

ENV PATH=~/.composer/vendor/bin:\$PATH

CMD if [ ! -f composer.lock ]; then $COMPOSER_REMOVE${COMPOSER_REMOVE:+ && }composer install; fi && \\
    vendor/bin/phpunit\${PHPUNIT_OPTS:+ }\$PHPUNIT_OPTS
"
