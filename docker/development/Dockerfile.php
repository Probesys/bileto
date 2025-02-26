# This file is part of Bileto.
# Copyright 2022-2025 Probesys
# SPDX-License-Identifier: AGPL-3.0-or-later

FROM php:8.2-fpm

ENV COMPOSER_HOME /tmp

RUN apt-get update && apt-get install -y \
    git \
    libc-client-dev \
    libicu-dev \
    libkrb5-dev \
    libldap-dev  \
    libpq-dev \
    libxslt-dev \
    libzip-dev \
    unzip \
  && pecl install xdebug \
  && docker-php-ext-configure intl \
  && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
  && docker-php-ext-install -j$(nproc) imap intl ldap pdo pdo_mysql pdo_pgsql xsl zip \
  && docker-php-ext-enable xdebug \
  && echo "xdebug.mode=coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini;

COPY --from=composer/composer /usr/bin/composer /usr/bin/composer
