<?php

/**
 * Laravel PHP built-in server router.
 * This file is used only when running: php -S localhost:8000 -t public server.php
 */

if ($uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '')) {
    // If the file exists in public, serve it directly (assets, etc.)
    if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
        return false;
    }
}

// Otherwise route everything through Laravel's front controller
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/public/index.php';
$_SERVER['SCRIPT_NAME']     = '/index.php';

require_once __DIR__.'/public/index.php';
