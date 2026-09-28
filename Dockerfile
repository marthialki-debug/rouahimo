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
 && rm -rf /var/lib/mysql/* \
 && mkdir -p /var/lib/mysql /run/mysqld \
 && chown mysql:mysql /var/lib/mysql /run/mysqld

# Low-memory MariaDB defaults for Render Free (~512MB)
RUN mkdir -p /etc/mysql/mariadb.conf.d \
 && printf '%s\n' \
      '[mysqld]' \
      'innodb_buffer_pool_size = 48M' \
      'innodb_log_buffer_size = 4M' \
      'key_buffer_size = 8M' \
      'max_connections = 30' \
      'table_open_cache = 64' \
      'thread_cache_size = 8' \
      'performance_schema = OFF' \
      'skip_name_resolve' \
      'bind-address = 127.0.0.1' \
      > /etc/mysql/mariadb.conf.d/99-render-lowmem.cnf

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
