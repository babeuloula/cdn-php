FROM bref/php-84-dev:3

ENV BREF_RUNTIME=fpm

COPY --from=bref/extra-imagick-php-84:3 /opt /opt
COPY --from=bref/extra-pcov-php-84:3 /opt /opt

RUN curl -sSL https://getcomposer.org/composer-2.phar -o /usr/local/bin/composer \
    && chmod +x /usr/local/bin/composer
