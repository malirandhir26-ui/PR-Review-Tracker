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
Commit. Then add docker-entrypoint.sh same way:
#!/usr/bin/env bash
set -e

SOCK=/run/mysqld/mysqld.sock

mkdir -p /run/mysqld
chown mysql:mysql /run/mysqld

if [ ! -d /var/lib/mysql/mysql ]; then
    mariadb-install-db --user=mysql --datadir=/var/lib/mysql \
        --auth-root-authentication-method=normal >/dev/null 2>&1 || \
    mysql_install_db --user=mysql --datadir=/var/lib/mysql >/dev/null 2>&1 || true
fi

mysqld --user=mysql --datadir=/var/lib/mysql \
    --socket=$SOCK --bind-address=127.0.0.1 --port=3306 --skip-networking=0 &
MYSQL_PID=$!

for i in $(seq 1 60); do
    if mariadb-admin --socket=$SOCK -u root ping >/dev/null 2>&1; then break; fi
    sleep 1
done

mariadb --socket=$SOCK -u root < /var/www/html/db.sql

COUNT=$(mariadb --socket=$SOCK -u root -N -e \
    "SELECT COUNT(*) FROM pr_review_tracker.pull_requests" 2>/dev/null || echo 0)
if [ "$COUNT" = "0" ]; then
    DB_HOST=127.0.0.1 DB_PORT=3306 php /var/www/html/seed_demo.php || true
fi

PORT=${PORT:-80}
sed -i "s/^Listen .*/Listen $PORT/" /etc/apache2/ports.conf
/usr/sbin/apache2ctl -D FOREGROUND &
APACHE_PID=$!

trap 'kill $MYSQL_PID $APACHE_PID 2>/dev/null' EXIT
wait $APACHE_PID
