FROM php:8.3-fpm-alpine

ARG DOCKER_PHP_EXT=2.7.23
ADD https://github.com/mlocati/docker-php-extension-installer/releases/download/${DOCKER_PHP_EXT}/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions

RUN apk add --no-cache \
        bash \
        bash-completion \
        g++ \
        gcc \
        git \
        gnupg \
        make \
        nano \
        unzip \
    && install-php-extensions \
        bcmath \
        decimal \
        intl \
        imagick \
        mcrypt \
        pcov \
        zip \
    && rm -rf /var/cache/apk/*

# Setting up PHP's timezone
ARG TZ
RUN echo "date.timezone = ${TZ}" > ${PHP_INI_DIR}/conf.d/timezone.ini

# Install composer
RUN curl -sSL https://getcomposer.org/composer-2.phar -o /usr/local/bin/composer \
    && chmod +x /usr/local/bin/composer

# Create user
ARG UID
RUN adduser -D -u ${UID} docker \
    && adduser docker www-data
COPY ./php/.bashrc /home/docker/.bashrc
RUN mkdir -p /srv \
    && chown -R docker:docker /home/docker \
    && chown -R docker /srv
USER docker

WORKDIR /srv
