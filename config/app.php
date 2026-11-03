<?php
/**
 * Konfigurasi Aplikasi - DAPUR TYAS by TECNO GARAGE
 */

// Enable Output Buffering
if (ob_get_level() === 0) {
    ob_start();
}

// Set Timezone Indonesia (WIB)
date_default_timezone_set('Asia/Jakarta');

// Load .env jika tersedia
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Environment (development / production)
if (!defined('ENVIRONMENT')) {
    $envVal = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? null);
    if ($envVal) {
        define('ENVIRONMENT', strtolower($envVal));
    } else {
        // Otomatis deteksi: jika diakses via domain publik/hosting, set production
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $isLocal = preg_match('/^(localhost|127\.0\.0\.1|192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/i', $host);
        define('ENVIRONMENT', $isLocal ? 'development' : 'production');
    }
}

// Error reporting disesuaikan dengan Environment
if (ENVIRONMENT === 'production') {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Identitas Brand Resmi
define('APP_NAME', 'DAPUR TYAS');
define('APP_TAGLINE', 'MASAKAN RUMAHAN YANG LEZAT');
define('APP_SUB_NAME', 'by TECNO GARAGE');
define('APP_VERSION', '1.0.0');

// Deteksi HTTPS (mendukung SSL langsung, cPanel/Cloudflare Reverse Proxy, & Load Balancer)
$isHttps = false;
if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
    $isHttps = true;
} elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
    $isHttps = true;
} elseif (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
    $isHttps = true;
} elseif (isset($_SERVER['HTTP_CF_VISITOR']) && str_contains($_SERVER['HTTP_CF_VISITOR'], '"https"')) {
    $isHttps = true;
} elseif (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
    $isHttps = true;
}

$protocol = $isHttps ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

// Hitung direktori root aplikasi di webserver (otomatis di root domain maupun subfolder)
$appRoot = preg_replace('#/(admin|kasir|customer|order|api|auth|config|includes)(/.*)?$#i', '', $scriptDir);
$appRoot = rtrim($appRoot, '/');

if (!defined('BASE_URL')) {
    define('BASE_URL', $protocol . $host . $appRoot);
}
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

// Default URL untuk akses pelanggan & QR Code: otomatis mengikuti BASE_URL
defined('LOCAL_BASE_URL_DEFAULT') or define('LOCAL_BASE_URL_DEFAULT', BASE_URL);

// Start session jika belum aktif
if (session_status() === PHP_SESSION_NONE) {
    // Session cookie security settings
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Load database & helper functions otomatis
require_once __DIR__ . '/database.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';

// Inisialisasi LOCAL_BASE_URL setelah database & helper functions dimuat
if (!defined('LOCAL_BASE_URL')) {
    define('LOCAL_BASE_URL', getLocalBaseUrl());
}
