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

# Install Dependencies
RUN apt install -y locales unixodbc libgss3 odbcinst devscripts debhelper dh-exec dh-autoreconf libreadline-dev libltdl-dev unixodbc-dev \
	&& rm -rf /var/lib/apt/lists/* \
	&& docker-php-ext-install pdo \
	&& echo "en_US.UTF-8 UTF-8" > /etc/locale.gen && locale-gen

# Add Microsoft repo for Microsoft ODBC Driver 17 for Linux
RUN curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add - \
	&& curl https://packages.microsoft.com/config/debian/9/prod.list > /etc/apt/sources.list.d/mssql-release.list \
	&& apt update

RUN ACCEPT_EULA=Y apt install -y apt-transport-https msodbcsql17

# Enable php sqlsrv extensions
RUN pecl install sqlsrv pdo_sqlsrv && docker-php-ext-enable sqlsrv pdo_sqlsrv

# Update APT & Clear cache
RUN apt clean && rm -rf /var/lib/apt/lists/* && apt update

# Update web root to public
# See: https://hub.docker.com/_/php#changing-documentroot-or-other-apache-configuration
ENV APACHE_DOCUMENT_ROOT /var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Enable mod_rewrite
RUN a2enmod rewrite

# Expose IP for Host
EXPOSE 8080