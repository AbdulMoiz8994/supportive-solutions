#!/bin/bash

# TailAdmin Laravel Pro - Production Fix Script
# This script fixes common deployment issues causing 500 errors

echo "========================================="
echo "TailAdmin Production Fix Script"
echo "========================================="
echo ""

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "❌ Error: Not in Laravel project root directory"
    exit 1
fi

echo "Step 1: Clearing all caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan clear-compiled
echo "✅ Caches cleared"
echo ""

echo "Step 2: Regenerating autoloader..."
composer dump-autoload --optimize
echo "✅ Autoloader regenerated"
echo ""

echo "Step 3: Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo "✅ Production optimizations applied"
echo ""

echo "Step 4: Checking permissions..."
if [ -w "storage/logs" ] && [ -w "bootstrap/cache" ]; then
    echo "✅ Permissions OK"
else
    echo "⚠️  Warning: Some directories may not be writable"
    echo "   Run: chmod -R 775 storage bootstrap/cache"
fi
echo ""

echo "========================================="
echo "Fix Complete!"
echo "========================================="
echo ""
echo "Next steps:"
echo "1. Visit your modal page: /modals"
echo "2. If still failing, check logs: tail -f storage/logs/laravel.log"
echo ""
