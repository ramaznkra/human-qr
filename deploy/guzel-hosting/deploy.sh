#!/usr/bin/env bash
# Deploy / güncelleme ( .env zaten var )
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

echo "==> Proje: $ROOT"

if [[ ! -f .env ]]; then
    echo "HATA: .env yok. İlk kurulum: bash deploy/install-production.sh"
    exit 1
fi

if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    php artisan key:generate --force
fi

echo "==> composer install (production)..."
export COMPOSER_ALLOW_SUPERUSER="${COMPOSER_ALLOW_SUPERUSER:-1}"
composer install --no-dev --optimize-autoloader --no-interaction

if ! command -v npm >/dev/null 2>&1; then
    echo "HATA: npm yok. bash deploy/guzel-hosting/install-node.sh"
    exit 1
fi

echo "==> npm build..."
if [[ -f package-lock.json ]]; then
    npm ci --ignore-scripts 2>/dev/null || npm install --ignore-scripts
else
    npm install --ignore-scripts
fi
npm run build

echo "==> migrate..."
php artisan migrate --force

php artisan storage:link 2>/dev/null || true
chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "OK — supervisorctl restart human-reverb human-queue"
