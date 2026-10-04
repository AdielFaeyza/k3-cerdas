#!/usr/bin/env bash
# Membuat proyek Laravel 11 di ./server lalu menimpa dengan file K3 dari ./backend
set -euo pipefail
cd "$(dirname "$0")/.."

composer create-project laravel/laravel:^11.0 server
cd server
php artisan install:api            # jawab "no" saat ditanya migrasi
php artisan install:broadcasting   # jawab "yes" untuk Reverb, "no" untuk dependensi Node
cp -r ../backend/. .
grep -v -E '^(APP_TIMEZONE|DB_CONNECTION|DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD|BROADCAST_CONNECTION)=' .env \
  | grep -v -E '^# ?DB_' > .env.tmp
cat .env.k3.example >> .env.tmp && mv .env.tmp .env && rm .env.k3.example
php artisan config:clear
php artisan migrate --seed
echo "Selesai. Buat akun dashboard: php artisan k3:user hse@perusahaan.co.id \"Tim HSE\" --role=hse"
