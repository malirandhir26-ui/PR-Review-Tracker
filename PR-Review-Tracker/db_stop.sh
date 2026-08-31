#!/usr/bin/env bash
# Stops the project-local MariaDB instance.
# Usage: ./db_stop.sh
set -e
cd "$(dirname "$0")"
PIDFILE="$PWD/.db/run/mysql.pid"

if [ -f "$PIDFILE" ]; then
    kill "$(cat "$PIDFILE")" 2>/dev/null || true
    rm -f "$PIDFILE"
    echo "MariaDB stopped."
else
    echo "MariaDB is not running."
fi
