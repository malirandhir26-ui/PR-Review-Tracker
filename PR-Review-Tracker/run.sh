#!/usr/bin/env bash
# One-command launcher for PR Review Tracker.
# Starts the local MariaDB (if needed) and the PHP dev server.
# Usage: ./run.sh   then open  http://localhost:8000
set -e
cd "$(dirname "$0")"

if [ ! -S .db/run/mysql.sock ]; then
    ./db_start.sh
fi

export PHP_INI_SCAN_DIR="$PWD/.php/conf.d"

echo "Starting PR Review Tracker at http://localhost:8000  (Ctrl+C to stop)"
exec php -d extension=pdo_mysql -S localhost:8000
