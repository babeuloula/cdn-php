FROM bref/php-83-fpm-dev:2
COPY --from=bref/extra-imagick-php-83:1 /opt /opt
COPY --from=bref/extra-pcov-php-83:1 /opt /opt

RUN curl -sSL https://getcomposer.org/composer-2.phar -o /usr/local/bin/composer \
    && chmod +x /usr/local/bin/composer
