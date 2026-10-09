<?php

/**
 * ==========================================================
 *  FILE: initialize.php
 *  DESCRIPTION: Define global constants & app configuration
 *  AUTHOR: GPT-5 🤖
 * ==========================================================
 */

// ==================
// Environment Mode
// ==================
// Secure by default. Set APP_ENV=development explicitly on a development PC.
if (!defined('APP_ENV')) define('APP_ENV', getenv('APP_ENV') ?: 'production');

// Handle CLI vs Web context
if (php_sapi_name() === 'cli') {
    // CLI context - use default values
    if (!defined('base_url')) define('base_url', 'http://localhost/sarinah-apps/apps/');
    if (!defined('base_app')) define('base_app', str_replace('\\', '/', __DIR__) . '/');
} else {
    // Prefer an explicitly configured URL in production. The validated Host
    // fallback keeps LAN/XAMPP installations working without trusting arbitrary
    // characters from the Host header.
    $configuredAppUrl = trim((string)(getenv('APP_URL') ?: ''));
    if ($configuredAppUrl !== '' && filter_var($configuredAppUrl, FILTER_VALIDATE_URL)) {
        if (!defined('base_url')) define('base_url', rtrim($configuredAppUrl, '/') . '/');
        if (!defined('base_app')) define('base_app', str_replace('\\', '/', __DIR__) . '/');
    } else {
    $trustProxyHeaders = filter_var(getenv('TRUST_PROXY_HEADERS') ?: '0', FILTER_VALIDATE_BOOLEAN);
    $scheme = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        ($trustProxyHeaders && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    ) ? 'https' : 'http';

    $requestHost = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    if (!preg_match('/\A(?:[a-z0-9.-]+|\[[a-f0-9:.]+\])(?::[0-9]{1,5})?\z/i', $requestHost)) {
        $requestHost = 'localhost';
    }

    if (!defined('base_url')) define('base_url', $scheme . '://' . $requestHost . '/sarinah-apps/apps/');
    if (!defined('base_app')) define('base_app', str_replace('\\', '/', __DIR__) . '/');
    }
}
// ==================
// Database Constants
// ==================
if (!defined('DB_SERVER'))   define('DB_SERVER', getenv('DB_SERVER') ?: '127.0.0.1');
if (!defined('DB_USERNAME')) define('DB_USERNAME', getenv('DB_USERNAME') ?: 'root');
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
if (!defined('DB_NAME'))     define('DB_NAME', getenv('DB_NAME') ?: 'sarinah');
if (!defined('DB_PORT'))     define('DB_PORT', getenv('DB_PORT') ?: 3306);

// Timezone
date_default_timezone_set('Asia/Jakarta');
