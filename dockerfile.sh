#!/usr/bin/env bash

if [[ -z "$PHP_RUNTIME" ]] ; then
    PHP_RUNTIME='php:7.2-cli'
fi

RUN_CMDS=''

if [[ $PHP_RUNTIME == php* ]]; then
    RUN_CMDS="$RUN_CMDS && \\\\\n    docker-php-ext-install zip mbstring"
    RUN_CMDS="$RUN_CMDS && \\\\\n    apt-get install -y libgmp-dev"
    RUN_CMDS="$RUN_CMDS && \\\\\n    ln -s /usr/include/x86_64-linux-gnu/gmp.h /usr/include/gmp.h && docker-php-ext-install gmp"
else
    RUN_CMDS="$RUN_CMDS && \\\\\n    echo 'hhvm.php7.all = 1' >> /etc/hhvm/php.ini"
fi

if [[ $PHPUNIT_OPTS =~ (^|[[:space:]])--coverage-[[:alpha:]] ]]; then
    RUN_CMDS="$RUN_CMDS && \\\\\n    git clone https://github.com/xdebug/xdebug.git /usr/src/php/ext/xdebug"
    RUN_CMDS="$RUN_CMDS && \\\\\n    echo xdebug >> /usr/src/php-available-exts && docker-php-ext-install xdebug"
fi

echo -e "
FROM $PHP_RUNTIME

RUN apt-get update && apt-get install -y git curl zlib1g-dev${RUN_CMDS}

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

ENV PATH=~/.composer/vendor/bin:\$PATH

CMD if [ ! -f composer.lock ]; then composer install; fi && vendor/bin/phpunit\${PHPUNIT_OPTS:+ }\$PHPUNIT_OPTS
"
