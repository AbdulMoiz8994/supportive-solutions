#!/usr/bin/env bash
set -euo pipefail

# Run from the Laravel app root on the staging server (stage.beydountech.com).
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ZIP_FILE="${APP_DIR}/staging-beydountech.zip"

cd "$APP_DIR"

if [[ ! -f "$ZIP_FILE" ]]; then
  echo "ERROR: ${ZIP_FILE} not found. Upload may have failed."
  exit 1
fi

echo "==> Extracting deployment package..."
unzip -o "$ZIP_FILE"

echo "==> Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> Running database migrations..."
php artisan migrate --force

echo "==> Ensuring public storage link..."
php artisan storage:link 2>/dev/null || true

echo "==> Clearing caches..."
php artisan optimize:clear

echo "==> Rebuilding Laravel caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
if php artisan list event:cache >/dev/null 2>&1; then
  php artisan event:cache
fi

echo "==> Restarting queue workers..."
php artisan queue:restart

echo "==> Restarting Reverb (if configured)..."
if php artisan list reverb:restart >/dev/null 2>&1; then
  php artisan reverb:restart 2>/dev/null || supervisorctl restart reverb 2>/dev/null || true
fi

echo "==> Optimizing application..."
php artisan optimize

echo "==> Setting writable directories..."
chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true

echo "==> Removing deployment package..."
rm -f "$ZIP_FILE"

echo "Deployment completed successfully at $(date -u +"%Y-%m-%dT%H:%M:%SZ")"