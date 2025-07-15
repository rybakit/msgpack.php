#!/usr/bin/env bash

if [[ -z "$PHP_IMAGE" ]]; then
    PHP_IMAGE='php:8.4-cli'
fi

RUN_CMDS=''
if [[ -n "$COVERAGE_FILE" ]]; then
    RUN_CMDS="$RUN_CMDS && \\\\\n    pecl install pcov && docker-php-ext-enable pcov"
fi

if [[ -z "$EXT_DISABLE_DECIMAL" || "0" == "$EXT_DISABLE_DECIMAL" || "false" == "$EXT_DISABLE_DECIMAL" ]] ; then
    # PHP 8.x images are based on Debian Bookworm, where the libmpdec-dev package
    # is not available, therefore the package has to be compiled from sources.
    # See https://github.com/docker-library/php/pull/1416.

    MPDEC_RELEASE_NAME="mpdecimal-2.5.1"
    MPDEC_URL="https://www.bytereef.org/software/mpdecimal/releases/$MPDEC_RELEASE_NAME.tar.gz"
    MPDEC_SHA256_SUM="9f9cd4c041f99b5c49ffb7b59d9f12d95b683d88585608aa56a6307667b2b21f"
    MPDEC_TMP_DIR="/tmp/$MPDEC_RELEASE_NAME"
    MPDEC_TMP_ARCHIVE="$MPDEC_TMP_DIR/$MPDEC_RELEASE_NAME.tar.gz"

    RUN_CMDS="$RUN_CMDS\\n\\nRUN"
    RUN_CMDS="$RUN_CMDS mkdir -p $MPDEC_TMP_DIR"
    RUN_CMDS="$RUN_CMDS && \\\\\n    cd $MPDEC_TMP_DIR"
    RUN_CMDS="$RUN_CMDS && \\\\\n    curl -LO $MPDEC_URL"
    RUN_CMDS="$RUN_CMDS && \\\\\n    echo \"$MPDEC_SHA256_SUM $MPDEC_TMP_ARCHIVE\" | sha256sum --check --status -"
    RUN_CMDS="$RUN_CMDS && \\\\\n    tar xf $MPDEC_TMP_ARCHIVE"
    RUN_CMDS="$RUN_CMDS && \\\\\n    cd $MPDEC_RELEASE_NAME"
    RUN_CMDS="$RUN_CMDS && \\\\\n    ./configure && make && make install"
    RUN_CMDS="$RUN_CMDS && \\\\\n    rm -rf $MPDEC_TMP_DIR"
    RUN_CMDS="$RUN_CMDS && \\\\\n    pecl install decimal && docker-php-ext-enable decimal"
fi

COMPOSER_REMOVE=''

echo -e "
FROM $PHP_IMAGE

RUN apt-get update && apt-get install -y curl git unzip libgmp-dev libonig-dev && \\
    git config --global --add safe.directory '*' && \\
    ln -s /usr/include/x86_64-linux-gnu/gmp.h /usr/include/gmp.h && \\
    echo memory_limit = 256M > \$(php -r 'echo PHP_CONFIG_FILE_SCAN_DIR;')/zz-custom.ini && \\
    docker-php-ext-install mbstring gmp${RUN_CMDS}

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

ENV PATH=~/.composer/vendor/bin:\$PATH

CMD if [ ! -f composer.lock ]; then ${COMPOSER_REMOVE:+composer remove --dev --no-update }$COMPOSER_REMOVE${COMPOSER_REMOVE:+ && }composer install; fi && \\
    vendor/bin/phpunit ${COVERAGE_FILE:+ --coverage-text --coverage-clover=}$COVERAGE_FILE
"
