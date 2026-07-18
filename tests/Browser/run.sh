#!/bin/bash
# Run E2E browser tests
# Requires: Laravel on :8000, Vite on :5173, Kolibri on :8090

set -e

echo "Checking services..."
curl -sf http://localhost:8000/ -o /dev/null 2>&1 || { echo "Laravel not running on :8000. Run ./start.sh first."; exit 1; }

echo "Running browser tests..."
npx playwright test "$@"
