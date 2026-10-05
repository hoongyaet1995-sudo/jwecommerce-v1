#!/bin/sh
set -eu
# Run from the private Laravel application root during each customer's deployment.
php artisan migrate --force --path=database/migrations/social
php artisan social-hub:prepare-pairing
