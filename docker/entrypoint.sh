#!/bin/bash
set -euo pipefail

PORT="${PORT:-80}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-rouahimo}"
DB_USER="${DB_USER:-rouahimo}"
DB_PASS="${DB_PASS:-rouahimo}"
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-rouahimo_root}"

# Apache ecoute le port Render ($PORT)
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s#<VirtualHost \*:.*>#<VirtualHost *:${PORT}>#" /etc/apache2/sites-available/000-default.conf

mkdir -p /var/www/html/uploads /var/www/html/logos /run/mysqld /var/lib/mysql
chown -R www-data:www-data /var/www/html/uploads /var/www/html/logos || true
chmod -R 775 /var/www/html/uploads /var/www/html/logos || true
chown mysql:mysql /run/mysqld /var/lib/mysql

mysql_as_root() {
  if mysql -u root -e "SELECT 1" >/dev/null 2>&1; then
    mysql -u root "$@"
  else
    mysql -u root -p"${MYSQL_ROOT_PASSWORD}" "$@"
  fi
}

dump_mysqld_logs() {
  echo "[rouahimo] --- /tmp/mysqld.err ---" >&2
  cat /tmp/mysqld.err 2>/dev/null || echo "(pas de /tmp/mysqld.err)" >&2
  echo "[rouahimo] --- /tmp/mysql_install.log ---" >&2
  cat /tmp/mysql_install.log 2>/dev/null || echo "(pas de /tmp/mysql_install.log)" >&2
}

start_local_mariadb() {
  echo "[rouahimo] Demarrage MariaDB local (low-mem Render Free)..."

  mkdir -p /run/mysqld /var/lib/mysql
  chown mysql:mysql /run/mysqld /var/lib/mysql

  if [ ! -d /var/lib/mysql/mysql ]; then
    echo "[rouahimo] Premiere initialisation datadir (/var/lib/mysql)..."
    if command -v mariadb-install-db >/dev/null 2>&1; then
      mariadb-install-db --user=mysql --datadir=/var/lib/mysql --auth-root-authentication-method=normal >/tmp/mysql_install.log 2>&1 \
        || { echo "[rouahimo] ERREUR: mariadb-install-db a echoue." >&2; dump_mysqld_logs; exit 1; }
    else
      mysql_install_db --user=mysql --datadir=/var/lib/mysql --auth-root-authentication-method=normal >/tmp/mysql_install.log 2>&1 \
        || { echo "[rouahimo] ERREUR: mysql_install_db a echoue." >&2; dump_mysqld_logs; exit 1; }
    fi
    echo "[rouahimo] Install DB OK (voir /tmp/mysql_install.log)."
  fi

  # Flags low-memory pour instance Render Free (~512MB)
  mysqld \
    --user=mysql \
    --datadir=/var/lib/mysql \
    --bind-address=127.0.0.1 \
    --port=3306 \
    --socket=/run/mysqld/mysqld.sock \
    --pid-file=/run/mysqld/mysqld.pid \
    --log-error=/tmp/mysqld.err \
    --innodb-buffer-pool-size=48M \
    --innodb-log-buffer-size=4M \
    --key-buffer-size=8M \
    --max-connections=30 \
    --table-open-cache=64 \
    --thread-cache-size=8 \
    --performance-schema=OFF \
    --skip-name-resolve \
    &
  MYSQL_PID=$!

  echo "[rouahimo] Attente MariaDB (pid=${MYSQL_PID})..."
  for i in $(seq 1 90); do
    if mysqladmin --socket=/run/mysqld/mysqld.sock ping --silent 2>/dev/null \
       || mysqladmin ping -h127.0.0.1 --silent 2>/dev/null; then
      break
    fi
    if ! kill -0 "$MYSQL_PID" 2>/dev/null; then
      echo "[rouahimo] ERREUR: mysqld est mort au demarrage." >&2
      dump_mysqld_logs
      exit 1
    fi
    sleep 1
  done

  if ! mysqladmin --socket=/run/mysqld/mysqld.sock ping --silent 2>/dev/null \
     && ! mysqladmin ping -h127.0.0.1 --silent 2>/dev/null; then
    echo "[rouahimo] ERREUR: MariaDB ne repond pas." >&2
    dump_mysqld_logs
    exit 1
  fi

  mysql_as_root -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '${MYSQL_ROOT_PASSWORD}'; FLUSH PRIVILEGES;" || true
  mysql_as_root -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  mysql_as_root -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
  mysql_as_root -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';" || true
  mysql_as_root -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost'; GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1'; FLUSH PRIVILEGES;" || true

  COUNT=$(mysql_as_root -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}';" 2>/dev/null || echo 0)
  COUNT=$(echo "$COUNT" | tr -d '[:space:]')
  if [ "${COUNT}" = "0" ] || [ -z "${COUNT}" ]; then
    echo "[rouahimo] Import sql/rouahimo.sql (premier demarrage)..."
    mysql_as_root < /var/www/html/sql/rouahimo.sql
    mysql_as_root -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost'; GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1'; FLUSH PRIVILEGES;" || true
    echo "[rouahimo] Import termine."
  else
    echo "[rouahimo] Base deja peuplee (${COUNT} tables) - pas de re-import."
  fi
}

case "${DB_HOST}" in
  127.0.0.1|localhost)
    start_local_mariadb
    ;;
  *)
    echo "[rouahimo] DB externe: ${DB_HOST}:${DB_PORT} - MariaDB local non demarre."
    ;;
esac

echo "[rouahimo] Apache sur le port ${PORT}"
exec apache2-foreground
