FROM php:8.2-apache-bookworm

# Extensions MySQL + MariaDB server (demo: un seul Web Service Render)
RUN apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
      mariadb-server mariadb-client \
      libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) mysqli pdo pdo_mysql gd \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/* \
 && mkdir -p /run/mysqld \
 && chown mysql:mysql /run/mysqld

WORKDIR /var/www/html

COPY . /var/www/html/

RUN mkdir -p /var/www/html/uploads /var/www/html/logos \
 && chown -R www-data:www-data /var/www/html/uploads /var/www/html/logos \
 && chmod -R 775 /var/www/html/uploads /var/www/html/logos \
 && rm -f /var/www/html/config.php \
 && chmod +x /var/www/html/docker/entrypoint.sh

# Defauts pour MariaDB dans le conteneur (surchargeables sur Render)
ENV DB_HOST=127.0.0.1 \
    DB_PORT=3306 \
    DB_NAME=rouahimo \
    DB_USER=rouahimo \
    DB_PASS=rouahimo \
    MYSQL_ROOT_PASSWORD=rouahimo_root \
    PORT=80

EXPOSE 80

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]