ARG PHP_VERSION

FROM php:$PHP_VERSION as base

ENV PROJECT_DIRECTORY=.
ENV SERVER_DIRECTORY=/var/www/html

# Fix debconf warnings upon build
ARG DEBIAN_FRONTEND=noninteractive

# Copy Whole Directory to the container
COPY ${PROJECT_DIRECTORY} ${SERVER_DIRECTORY}

# Set working directory
WORKDIR ${SERVER_DIRECTORY}

RUN chown -R www-data:www-data ${SERVER_DIRECTORY}
RUN chmod 577 ${SERVER_DIRECTORY}/.env

# Install system dependencies
RUN apt update && apt upgrade -y

# Update web root to public
# See: https://hub.docker.com/_/php#changing-documentroot-or-other-apache-configuration
ENV APACHE_DOCUMENT_ROOT /var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Enable mod_rewrite
RUN a2enmod rewrite

# Update APT & Clear cache
RUN apt clean && rm -rf /var/lib/apt/lists/* && apt update

EXPOSE 8080