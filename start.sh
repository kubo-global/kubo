#!/bin/bash

# Start all KUBO services: Kolibri, Laravel, Vite.
# Reconciles Kolibri config first so a fresh install lands in the same
# state as the production Pi (no manual setup wizard tweaks).

set -u
trap 'echo "Stopping..."; kill 0; exit' INT TERM

KOLIBRI_PORT="${KOLIBRI_PORT:-8090}"

echo "Starting Kolibri on :${KOLIBRI_PORT}..."
if curl -s -o /dev/null -w "%{http_code}" "http://localhost:${KOLIBRI_PORT}/api/public/info/" | grep -q 200; then
    echo "  Kolibri already running"
else
    python3 -m kolibri start --port "${KOLIBRI_PORT}" &
    echo -n "  waiting for Kolibri"
    for _ in $(seq 1 60); do
        if curl -s -o /dev/null -w "%{http_code}" "http://localhost:${KOLIBRI_PORT}/api/public/info/" | grep -q 200; then
            echo " ready"
            break
        fi
        echo -n "."
        sleep 1
    done
fi

echo "Reconciling Kolibri config..."
php artisan kolibri:reconcile || {
    echo "  reconcile failed — fix the issue above before continuing"
    kill 0
    exit 1
}

echo "Starting Laravel on :8000..."
php artisan serve --port=8000 &

echo "Starting Vite..."
npm run dev &

echo ""
echo "  Laravel:  http://localhost:8000"
echo "  Vite:     http://localhost:5173"
echo "  Kolibri:  http://localhost:${KOLIBRI_PORT}"
echo ""
echo "Press Ctrl+C to stop all"

wait
