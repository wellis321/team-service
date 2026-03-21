#!/usr/bin/env bash
# Start the Team Service development server on localhost:8001
cd "$(dirname "$0")"
echo "Starting Team Service on http://localhost:8001"
php -S localhost:8001 -t public/
