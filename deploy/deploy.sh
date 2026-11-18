#!/usr/bin/env bash
# Jalankan di server sebagai user deploy (punya sudo untuk reload php-fpm/supervisor).
#   cd /var/www/evident-kinerja && ./deploy/deploy.sh
set -euo pipefail
cd "$(dirname "$0")/.."

[ -f .env ] || { echo ".env belum ada (salin dari deploy/.env.production.example)"; exit 1; }

git fetch --quiet origin
git merge --ff-only "@{u}"

composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

php artisan down --retry=30 --refresh=5 || true
trap 'php artisan up' EXIT

php artisan migrate --force
php artisan storage:link --quiet || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Horizon: selesaikan job upload yang berjalan, lalu supervisor menjalankan versi kode baru.
php artisan horizon:terminate
sudo systemctl reload php8.3-fpm   # reset opcache (validate_timestamps=0)

echo "Deploy selesai: $(git rev-parse --short HEAD)"
