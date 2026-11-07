<?php

/**
 * LABVERSE v2.0 — Self-Healing Shared Hosting / cPanel Pre-Boot Guard
 * Prevents HTTP 500 errors on fresh cPanel/VPS deployments by:
 * 1. Checking PHP version (>= 8.2) before Composer/Symfony syntax loads
 * 2. Auto-extracting bootstrap/vendor-runtime.zip if vendor/autoload.php is missing (e.g. Git clone or partial upload)
 * 3. Auto-creating required storage, cache, and database directories
 * 4. Ensuring .env exists with a valid APP_KEY and file-based session/cache drivers before DB install
 * 5. Normalizing SCRIPT_NAME when hosted in a cPanel root folder without /public document root
 */

$basePath = realpath(__DIR__ . '/..');

// 1. Friendly PHP Version Check (Prevents blank HTTP 500 on PHP 7.4 / 8.0 / 8.1 cPanel defaults)
if (PHP_VERSION_ID < 80200) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>LABVERSE — PHP Version Upgrade Required</title>';
    echo '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#F5F7FA;color:#1E293B;padding:40px 20px;margin:0;}';
    echo '.card{max-width:640px;margin:0 auto;background:#FFF;border:1px solid #E2E8F0;border-top:4px solid #164E8A;border-radius:10px;padding:28px;box-shadow:0 4px 12px rgba(0,0,0,0.05);}';
    echo 'code{background:#F1F5F9;padding:2px 6px;border-radius:4px;font-family:monospace;}</style></head><body>';
    echo '<div class="card"><h2 style="margin-top:0;color:#164E8A;">LABVERSE v2.0 — Server PHP Version Notice</h2>';
    echo '<p>Your web server is currently running <strong>PHP ' . htmlspecialchars(PHP_VERSION) . '</strong>, but LABVERSE requires <strong>PHP 8.2, 8.3, or 8.4+</strong>.</p>';
    echo '<h4>How to fix this in 30 seconds on cPanel:</h4>';
    echo '<ol style="line-height:1.7;"><li>Log in to your <strong>cPanel</strong> dashboard.</li>';
    echo '<li>Open <strong>MultiPHP Manager</strong> or <strong>Select PHP Version</strong>.</li>';
    echo '<li>Select your domain (<code>' . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'your-domain') . '</code>) and switch PHP to <strong>PHP 8.2, 8.3, or 8.4</strong>.</li>';
    echo '<li>Refresh this page to launch the <strong>LABVERSE Visual Setup Wizard</strong>!</li></ol></div></body></html>';
    exit;
}

// 2. Self-Healing Vendor Auto-Extractor (If deployed via Git or if vendor/autoload.php is missing)
$autoloadPath = $basePath . '/vendor/autoload.php';
$vendorZipPath = $basePath . '/bootstrap/vendor-runtime.zip';

if (!file_exists($autoloadPath) && file_exists($vendorZipPath) && class_exists('ZipArchive')) {
    @ini_set('max_execution_time', '120');
    @ini_set('memory_limit', '512M');
    $zip = new ZipArchive();
    if ($zip->open($vendorZipPath) === true) {
        @$zip->extractTo($basePath);
        $zip->close();
    }
}

// 3. Auto-create required writable directories if missing after ZIP extraction
$requiredDirs = [
    $basePath . '/storage',
    $basePath . '/storage/app',
    $basePath . '/storage/app/public',
    $basePath . '/storage/framework',
    $basePath . '/storage/framework/cache',
    $basePath . '/storage/framework/cache/data',
    $basePath . '/storage/framework/sessions',
    $basePath . '/storage/framework/testing',
    $basePath . '/storage/framework/views',
    $basePath . '/storage/logs',
    $basePath . '/bootstrap/cache',
    $basePath . '/database',
];

foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

// 4. Remove stale cached config/packages if they reference missing dev providers or foreign paths
$cachedConfig = $basePath . '/bootstrap/cache/config.php';
if (file_exists($cachedConfig)) {
    $cfgContent = (string) @file_get_contents($cachedConfig);
    if ($cfgContent !== '' && !str_contains($cfgContent, str_replace('\\', '/', $basePath)) && !str_contains($cfgContent, $basePath)) {
        @unlink($cachedConfig);
    }
}

$cachedPackages = $basePath . '/bootstrap/cache/packages.php';
if (file_exists($cachedPackages)) {
    $pkgContent = (string) @file_get_contents($cachedPackages);
    if (
        (str_contains($pkgContent, 'CollisionServiceProvider') && !is_dir($basePath . '/vendor/nunomaduro/collision')) ||
        (str_contains($pkgContent, 'PailServiceProvider') && !is_dir($basePath . '/vendor/laravel/pail'))
    ) {
        @unlink($cachedPackages);
        @unlink($basePath . '/bootstrap/cache/services.php');
    }
}

// 5. Auto-initialize .env with a valid APP_KEY and safe file-based session/cache drivers
$envFile = $basePath . '/.env';
$envExample = $basePath . '/.env.example';

if (!file_exists($envFile) && file_exists($envExample)) {
    @copy($envExample, $envFile);
}

if (file_exists($envFile)) {
    $envContent = (string) @file_get_contents($envFile);
    $modified = false;

    if (!preg_match('/^APP_KEY=base64:.+/m', $envContent)) {
        $newKey = 'base64:' . base64_encode(random_bytes(32));
        if (preg_match('/^APP_KEY=.*$/m', $envContent)) {
            $envContent = (string) preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $newKey, $envContent);
        } else {
            $envContent .= "\nAPP_KEY=" . $newKey . "\n";
        }
        $modified = true;
    }

    if (preg_match('/^SESSION_DRIVER=database/m', $envContent)) {
        $envContent = (string) preg_replace('/^SESSION_DRIVER=database/m', 'SESSION_DRIVER=file', $envContent);
        $modified = true;
    }
    if (preg_match('/^CACHE_STORE=database/m', $envContent)) {
        $envContent = (string) preg_replace('/^CACHE_STORE=database/m', 'CACHE_STORE=file', $envContent);
        $modified = true;
    }
    if (preg_match('/^QUEUE_CONNECTION=database/m', $envContent)) {
        $envContent = (string) preg_replace('/^QUEUE_CONNECTION=database/m', 'QUEUE_CONNECTION=sync', $envContent);
        $modified = true;
    }

    if ($modified && is_writable($envFile)) {
        @file_put_contents($envFile, $envContent);
    }
}

// 6. Ensure SQLite file exists if SQLite is configured (prevents PDOException before /install runs)
$sqliteFile = $basePath . '/database/database.sqlite';
if (!file_exists($sqliteFile)) {
    @touch($sqliteFile);
    @chmod($sqliteFile, 0664);
}

// 7. Normalize SCRIPT_NAME when cPanel .htaccess rewrites from root to public/index.php
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
if (str_ends_with($scriptName, '/public/index.php') && !str_starts_with($requestUri, '/public')) {
    $normalizedScript = substr($scriptName, 0, -strlen('/public/index.php')) . '/index.php';
    $_SERVER['SCRIPT_NAME'] = $normalizedScript ?: '/index.php';
    $_SERVER['PHP_SELF'] = $normalizedScript ?: '/index.php';
}

/**
 * Renders a user-friendly diagnostic screen instead of a blank HTTP 500 if an uncaught boot error occurs.
 */
function labverseRenderBootError(\Throwable $e): void
{
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>LABVERSE — Server Setup Diagnostic</title>';
    echo '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#F5F7FA;color:#1E293B;padding:40px 20px;margin:0;}';
    echo '.card{max-width:720px;margin:0 auto;background:#FFF;border:1px solid #E2E8F0;border-top:4px solid #DC2626;border-radius:10px;padding:28px;box-shadow:0 4px 12px rgba(0,0,0,0.05);}';
    echo '.btn{display:inline-block;background:#164E8A;color:#FFF;text-decoration:none;padding:10px 18px;border-radius:6px;font-weight:600;margin-top:12px;}';
    echo 'pre{background:#F8FAFC;border:1px solid #E2E8F0;padding:12px;border-radius:6px;overflow-x:auto;font-size:12px;color:#334155;}</style></head><body>';
    echo '<div class="card"><h2 style="margin-top:0;color:#1E293B;">LABVERSE v2.0 — Server Diagnostic</h2>';
    echo '<p>An environment configuration error was detected while starting the application:</p>';
    echo '<pre>' . htmlspecialchars($e->getMessage() . "\nIn " . basename($e->getFile()) . ':' . $e->getLine()) . '</pre>';
    echo '<p style="font-size:14px;color:#64748B;">Verify that the full <strong>labverse-v2.0-ready-to-deploy.zip</strong> archive (which includes the <code>vendor/</code> folder and <code>bootstrap/vendor-runtime.zip</code>) was uploaded and extracted into <code>public_html</code>.</p>';
    echo '<a href="/install" class="btn">Open Visual Setup Wizard (/install) &rarr;</a></div></body></html>';
}
