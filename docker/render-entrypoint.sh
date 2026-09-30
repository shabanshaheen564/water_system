#!/bin/sh
set -e

php artisan optimize:clear
php artisan permission:cache-reset

exec apache2-foreground
