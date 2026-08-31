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

RUN a2enmod rewrite headers && \
    printf '#!/bin/bash\n\
set -e\n\
SOCK=/run/mysqld/mysqld.sock\n\
mkdir -p /run/mysqld\n\
chown mysql:mysql /run/mysqld\n\
if [ ! -d /var/lib/mysql/mysql ]; then\n\
    mariadb-install-db --user=mysql --datadir=/var/lib/mysql --auth-root-authentication-method=normal >/dev/null 2>&1 || true\n\
fi\n\
mysqld --user=mysql --datadir=/var/lib/mysql --socket=$SOCK --bind-address=127.0.0.1 --port=3306 &\n\
MYSQL_PID=$!\n\
for i in $(seq 1 60); do\n\
    if mariadb-admin --socket=$SOCK -u root ping >/dev/null 2>&1; then break; fi\n\
    sleep 1\n\
done\n\
mariadb --socket=$SOCK -u root < /var/www/html/db.sql\n\
COUNT=$(mariadb --socket=$SOCK -u root -N -e "SELECT COUNT(*) FROM pr_review_tracker.pull_requests" 2>/dev/null || echo 0)\n\
if [ "$COUNT" = "0" ]; then\n\
    DB_HOST=127.0.0.1 DB_PORT=3306 php /var/www/html/seed_demo.php || true\n\
fi\n\
PORT=${PORT:-80}\n\
sed -i "s/^Listen .*/Listen $PORT/" /etc/apache2/ports.conf\n\
/usr/sbin/apache2ctl -D FOREGROUND &\n\
APACHE_PID=$!\n\
trap "kill $MYSQL_PID $APACHE_PID 2>/dev/null" EXIT\n\
wait $APACHE_PID\n' > /var/www/html/entrypoint.sh && chmod +x /var/www/html/entrypoint.sh

ENTRYPOINT ["/var/www/html/entrypoint.sh"]
