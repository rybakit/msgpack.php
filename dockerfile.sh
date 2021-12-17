#!/usr/bin/env bash

if [[ -z "$PHP_IMAGE" ]]; then
    PHP_IMAGE='php:8.1-cli'
fi

RUN_CMDS=''
if [[ -z "$EXT_DISABLE_DECIMAL" || "0" == "$EXT_DISABLE_DECIMAL" || "false" == "$EXT_DISABLE_DECIMAL" ]] ; then
  RUN_CMDS="$RUN_CMDS && \\\\\n    apt-get install -y libmpdec-dev"
  RUN_CMDS="$RUN_CMDS && \\\\\n    pecl install decimal && docker-php-ext-enable decimal"
fi

if [[ -n "$COVERAGE_FILE" ]]; then
    RUN_CMDS="$RUN_CMDS && \\\\\n    pecl install pcov && docker-php-ext-enable pcov"
fi

COMPOSER_REMOVE=''

echo -e "
FROM $PHP_IMAGE

RUN apt-get update && apt-get install -y curl git unzip libgmp-dev libonig-dev && \\
    ln -s /usr/include/x86_64-linux-gnu/gmp.h /usr/include/gmp.h && \\
    echo memory_limit = 256M > \$(php -r 'echo PHP_CONFIG_FILE_SCAN_DIR;')/zz-custom.ini && \\
    docker-php-ext-install mbstring gmp${RUN_CMDS}

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

ENV PATH=~/.composer/vendor/bin:\$PATH

CMD if [ ! -f composer.lock ]; then ${COMPOSER_REMOVE:+composer remove --dev --no-update }$COMPOSER_REMOVE${COMPOSER_REMOVE:+ && }composer install; fi && \\
    vendor/bin/phpunit ${COVERAGE_FILE:+ --coverage-text --coverage-clover=}$COVERAGE_FILE
"
