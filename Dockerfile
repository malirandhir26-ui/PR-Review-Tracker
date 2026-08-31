FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        mariadb-server \
        libcurl4-openssl-dev \
        pkg-config \
    && docker-php-ext-install pdo_mysql curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . .

RUN a2enmod rewrite headers

EXPOSE 80

ENTRYPOINT ["/var/www/html/docker-entrypoint.sh"]
