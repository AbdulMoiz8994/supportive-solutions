#!/bin/bash

# TailAdmin Laravel Pro - Production Troubleshooting Script
# This script helps diagnose common deployment issues

echo "==================================="
echo "TailAdmin Production Diagnostics"
echo "==================================="
echo ""

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "❌ Error: Not in Laravel project root directory"
    exit 1
fi

echo "1. Checking Vite Build Assets..."
if [ -f "public/build/manifest.json" ]; then
    echo "✅ Vite manifest.json exists"
    echo "   Location: public/build/manifest.json"
    echo "   Size: $(du -h public/build/manifest.json | cut -f1)"
else
    echo "❌ Vite manifest.json NOT FOUND"
    echo "   This is likely causing 500 errors on your pages"
    echo "   Solution: Run 'npm run build' to generate assets"
fi
echo ""

echo "2. Checking Build Directory..."
if [ -d "public/build/assets" ]; then
    asset_count=$(ls -1 public/build/assets | wc -l)
    echo "✅ Build assets directory exists"
    echo "   Asset files: $asset_count"
else
    echo "❌ Build assets directory NOT FOUND"
    echo "   Solution: Run 'npm run build'"
fi
echo ""

echo "3. Checking Environment Configuration..."
if [ -f ".env" ]; then
    echo "✅ .env file exists"
    
    # Check APP_ENV
    app_env=$(grep "^APP_ENV=" .env | cut -d'=' -f2)
    if [ "$app_env" = "production" ]; then
        echo "✅ APP_ENV=production"
    else
        echo "⚠️  APP_ENV=$app_env (should be 'production' for production)"
    fi
    
    # Check APP_DEBUG
    app_debug=$(grep "^APP_DEBUG=" .env | head -1 | cut -d'=' -f2)
    if [ "$app_debug" = "false" ]; then
        echo "✅ APP_DEBUG=false"
    else
        echo "⚠️  APP_DEBUG=$app_debug (should be 'false' for production)"
    fi
    
    # Check APP_URL
    app_url=$(grep "^APP_URL=" .env | cut -d'=' -f2)
    if [[ $app_url == https://* ]]; then
        echo "✅ APP_URL uses HTTPS: $app_url"
    else
        echo "⚠️  APP_URL: $app_url (should use HTTPS for production)"
    fi
else
    echo "❌ .env file NOT FOUND"
fi
echo ""

echo "4. Checking Node.js and npm..."
if command -v node &> /dev/null; then
    node_version=$(node --version)
    echo "✅ Node.js installed: $node_version"
else
    echo "❌ Node.js NOT FOUND"
fi

if command -v npm &> /dev/null; then
    npm_version=$(npm --version)
    echo "✅ npm installed: $npm_version"
else
    echo "❌ npm NOT FOUND"
fi
echo ""

echo "5. Checking Laravel Logs..."
if [ -f "storage/logs/laravel.log" ]; then
    log_size=$(du -h storage/logs/laravel.log | cut -f1)
    echo "✅ Laravel log exists (Size: $log_size)"
    
    # Check for recent Vite manifest errors
    vite_errors=$(grep -c "Vite manifest not found" storage/logs/laravel.log 2>/dev/null || echo "0")
    if [ "$vite_errors" -gt "0" ]; then
        echo "❌ Found $vite_errors 'Vite manifest not found' errors in logs"
        echo "   Last occurrence:"
        grep "Vite manifest not found" storage/logs/laravel.log | tail -1
    else
        echo "✅ No Vite manifest errors in logs"
    fi
else
    echo "⚠️  No Laravel log file found"
fi
echo ""

echo "6. Checking Storage Permissions..."
if [ -w "storage/logs" ]; then
    echo "✅ storage/logs is writable"
else
    echo "❌ storage/logs is NOT writable"
    echo "   Solution: chmod -R 775 storage"
fi
echo ""

echo "==================================="
echo "Summary & Recommendations"
echo "==================================="
echo ""

# Determine if build is needed
if [ ! -f "public/build/manifest.json" ]; then
    echo "🔧 ACTION REQUIRED: Build frontend assets"
    echo "   Run: npm ci && npm run build"
    echo ""
fi

# Check environment
if [ "$app_env" != "production" ] || [ "$app_debug" != "false" ]; then
    echo "🔧 ACTION REQUIRED: Update environment variables"
    echo "   Set APP_ENV=production and APP_DEBUG=false in Coolify"
    echo ""
fi

echo "📖 For detailed troubleshooting, see DEPLOYMENT.md"
echo ""
