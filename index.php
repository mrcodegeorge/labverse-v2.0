<?php

/**
 * LABVERSE v2.0 — Root Entrypoint for cPanel / Shared Web Hosting
 * Allows extracting the project directly into public_html/ or a subdomain root folder
 * without needing to reconfigure the web server's Document Root to /public.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$publicFile = __DIR__ . '/public' . $uri;

// Serve static assets (CSS, JS, images, icons) from /public if accessed via root PHP fallback
if ($uri !== '/' && is_file($publicFile) && !str_ends_with(strtolower($publicFile), '.php')) {
    $ext = strtolower(pathinfo($publicFile, PATHINFO_EXTENSION));
    $mimeMap = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'ico' => 'image/x-icon',
        'woff2' => 'font/woff2',
        'woff' => 'font/woff',
    ];
    if (isset($mimeMap[$ext])) {
        header('Content-Type: ' . $mimeMap[$ext]);
    }
    readfile($publicFile);
    exit;
}

require_once __DIR__ . '/public/index.php';

