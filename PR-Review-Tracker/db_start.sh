#!/usr/bin/env bash
# Starts the project-local MariaDB instance.
# Usage: ./db_start.sh
set -e
cd "$(dirname "$0")"
mkdir -p .db/run .db/log

if [ -S .db/run/mysql.sock ]; then
    echo "MariaDB already running (socket: .db/run/mysql.sock)."
    exit 0
fi

setsid nohup mariadbd \
  --datadir="$PWD/.db/data" \
  --socket="$PWD/.db/run/mysql.sock" \
  --port=3307 \
  --bind-address=127.0.0.1 \
  --pid-file="$PWD/.db/run/mysql.pid" \
  --log-error="$PWD/.db/log/mysql.log" \
  --user="$USER" > /dev/null 2>&1 &

for i in $(seq 1 30); do
    [ -S "$PWD/.db/run/mysql.sock" ] && break
    sleep 0.5
done

if [ -S "$PWD/.db/run/mysql.sock" ]; then
    echo "MariaDB ready. Socket: .db/run/mysql.sock (port 3307)"
else
    echo "MariaDB failed to start. Check .db/log/mysql.log"
    exit 1
fi
