<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Run LABVERSE Self-Healing cPanel / Shared Hosting Pre-Boot Guard
require_once __DIR__ . '/../bootstrap/server-guard.php';

try {
    // Determine if the application is in maintenance mode...
    if (file_exists($maintenance = __DIR__ . '/../storage/framework/maintenance.php')) {
        require $maintenance;
    }

    // Register the Composer autoloader...
    require __DIR__ . '/../vendor/autoload.php';

    // Bootstrap Laravel and handle the request...
    /** @var Application $app */
    $app = require_once __DIR__ . '/../bootstrap/app.php';

    $app->handleRequest(Request::capture());
} catch (\Throwable $e) {
    labverseRenderBootError($e);
}
