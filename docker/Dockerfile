# This file is part of Bileto.
# Copyright 2022-2023 Probesys
# SPDX-License-Identifier: AGPL-3.0-or-later

FROM php:8.1-fpm

ENV COMPOSER_HOME /tmp

RUN apt-get update && apt-get install -y \
    git \
    libicu-dev \
    libzip-dev \
    unzip \
    libpq-dev \
    libxslt-dev \
  && pecl install xdebug \
  && docker-php-ext-configure intl \
  && docker-php-ext-install -j$(nproc) intl zip pdo pdo_pgsql pdo_mysql xsl \
  && docker-php-ext-enable xdebug \
  && echo "xdebug.mode=coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini;

COPY --from=composer/composer /usr/bin/composer /usr/bin/composer
