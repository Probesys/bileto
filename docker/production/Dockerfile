# This file is part of Bileto.
# Copyright 2022-2023 Probesys
# SPDX-License-Identifier: AGPL-3.0-or-later

# Build the assets
FROM node:alpine AS assets

WORKDIR /app
COPY package*.json /app
COPY .vite/ /app/.vite
COPY assets/ /app/assets
RUN npm install && npm run build

# Configure the application
FROM php:8.2-apache

ENV APP_ENV prod
ENV COMPOSER_HOME /tmp
ENV TZ Europe/Paris

CMD ["apache2-foreground"]
ENTRYPOINT ["sh", "/entrypoint.sh"]

ARG VERSION
ARG SOURCE_COMMIT

LABEL org.opencontainers.image.authors="Probesys <https://www.probesys.com/contact>" \
      org.opencontainers.image.url="https://bileto.fr/" \
      org.opencontainers.image.documentation="https://github.com/Probesys/bileto/tree/main/docs" \
      org.opencontainers.image.source="https://github.com/Probesys/bileto" \
      org.opencontainers.image.version="${VERSION}" \
      org.opencontainers.image.revision="${SOURCE_COMMIT}" \
      org.opencontainers.image.vendor="Probesys" \
      org.opencontainers.image.licenses="AGPL-3.0-or-later" \
      org.opencontainers.image.title="Bileto" \
      org.opencontainers.image.description="The ergonomic ticketing tool for managing your Help Desk."

# Install Composer
COPY --from=composer/composer /usr/bin/composer /usr/bin/composer

# Configure Apache
COPY docker/production/apache.conf /etc/apache2/sites-available/000-default.conf

# Install the dependencies
RUN apt-get update \
    && apt-get install -y \
        libicu-dev \
        libldap-dev  \
        libpq-dev \
        libxslt-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j$(nproc) \
        intl \
        ldap \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        xsl \
        zip \
    && a2dismod -qf alias \
    && a2enmod rewrite \
    && ln -snf /usr/share/zoneinfo/$TZ /etc/localtime \
    && echo $TZ > /etc/timezone \
    && printf '[PHP]\ndate.timezone = ${TZ}\n' > /usr/local/etc/php/conf.d/timezone.ini

# Copy the code in the image
COPY . /var/www/html/
RUN rm -rf /var/www/html/public/assets
COPY --from=assets /app/public/assets/ /var/www/html/public/assets/
COPY --from=assets /app/public/manifest.json /var/www/html/public/
COPY docker/production/entrypoint.sh /entrypoint.sh

# Install the Composer dependencies and set correct permissions
RUN composer install --no-dev --optimize-autoloader --no-scripts \
    && mkdir /var/www/html/var \
    && mkdir /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/

USER www-data
