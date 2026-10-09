<?php

/**
 * ==========================================================
 *  FILE: config.php
 *  DESCRIPTION: Global app configuration & helpers
 *  AUTHOR: GPT-5 🤖
 * ==========================================================
 */

function is_https_request()
{
    $trustProxyHeaders = filter_var(getenv('TRUST_PROXY_HEADERS') ?: '0', FILTER_VALIDATE_BOOLEAN);
    return (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['SERVER_PORT'] ?? null) == 443) ||
        ($trustProxyHeaders && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    );
}

function start_secure_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => is_https_request(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/; samesite=Lax', '', is_https_request(), true);
    }

    @session_start();
}

function apply_security_headers()
{
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 0');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('Origin-Agent-Cluster: ?1');

    $csp = [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'self'",
        "form-action 'self'",
        "script-src 'self' 'unsafe-inline'",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' data: https://fonts.gstatic.com",
        "img-src 'self' data: blob:",
        "connect-src 'self'",
        "media-src 'self' blob:",
        "worker-src 'self' blob:",
        "frame-src 'self' blob:"
    ];
    if (defined('APP_ENV') && APP_ENV === 'production' && is_https_request()) {
        $csp[] = 'upgrade-insecure-requests';
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header('Content-Security-Policy: ' . implode('; ', $csp));

    if (!empty($_SESSION['userdata'])) {
        header('Cache-Control: no-store, private, max-age=0');
        header('Pragma: no-cache');
    }
}

if (php_sapi_name() !== 'cli') {
    start_secure_session();
    apply_security_headers();
}

// Timezone setup
ini_set('date.timezone', 'Asia/Jakarta');
date_default_timezone_set('Asia/Jakarta');

// Load core dependencies
require_once('initialize.php');
require_once('classes/DBConnection.php');
require_once('classes/SystemSettings.php');

// ======================
// Database Connection
// ======================
try {
    $db = new DBConnection();
    // Trigger lazy connection by calling a public method
    $db->escape('');
    $conn = $db->conn; // Now $conn is a proper mysqli object
} catch (Exception $e) {
    if (APP_ENV === 'development') {
        die("<pre style='color:red'><b>Database Error:</b> " . $e->getMessage() . "</pre>");
    } else {
        error_log($e->getMessage(), 3, __DIR__ . '/logs/db_error.log');
        die("<h3>⚠️ Terjadi gangguan koneksi database. Silakan coba lagi.</h3>");
    }
}

// ======================
// Error Display Control
// ======================
if (APP_ENV === 'production') {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/php-error.log');
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// ======================
// Global Helper Functions
// ======================

function redirect($url = '')
{
    if ($url === '') {
        return;
    }

    $url = str_replace(["\r", "\n"], '', (string)$url);
    $target = base_url . ltrim($url, '/');

    if (!headers_sent()) {
        header('Location: ' . $target);
    } else {
        echo '<script>location.href=' . json_encode($target) . '</script>';
    }
    exit;
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Validasi upload gambar secara fail-closed berdasarkan isi file, bukan nama.
 * Mengembalikan MIME, ekstensi, dan dimensi yang sudah terverifikasi.
 */
function validate_uploaded_image(array $file, array $allowedMime, $maxBytes, $maxWidth = 8000, $maxHeight = 8000, $maxPixels = 25000000)
{
    foreach (['error', 'size', 'tmp_name'] as $key) {
        if (!array_key_exists($key, $file) || is_array($file[$key])) {
            throw new RuntimeException('Struktur file upload tidak valid.');
        }
    }

    $error = (int)$file['error'];
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
            ? 'Ukuran file melebihi batas server.'
            : 'Upload file gagal. Silakan pilih ulang file.');
    }

    $fileSize = (int)$file['size'];
    $tmpName = (string)$file['tmp_name'];
    if ($fileSize <= 0 || $fileSize > (int)$maxBytes || !is_file($tmpName) || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('File upload kosong, terlalu besar, atau bukan upload HTTP yang sah.');
    }

    $imageInfo = @getimagesize($tmpName);
    if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1]) || empty($imageInfo[2])) {
        throw new RuntimeException('Isi file bukan gambar yang valid.');
    }

    $typeMap = [
        IMAGETYPE_JPEG => ['image/jpeg', 'jpg'],
        IMAGETYPE_PNG => ['image/png', 'png'],
        IMAGETYPE_WEBP => ['image/webp', 'webp'],
    ];
    $imageType = (int)$imageInfo[2];
    if (!isset($typeMap[$imageType])) {
        throw new RuntimeException('Format gambar tidak diizinkan.');
    }

    [$expectedMime, $extension] = $typeMap[$imageType];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = (string)$finfo->file($tmpName);
    if ($detectedMime !== $expectedMime || !in_array($detectedMime, $allowedMime, true)) {
        throw new RuntimeException('Tipe file tidak sesuai dengan isi gambar.');
    }

    $width = (int)$imageInfo[0];
    $height = (int)$imageInfo[1];
    if ($width > (int)$maxWidth || $height > (int)$maxHeight || ($width * $height) > (int)$maxPixels) {
        throw new RuntimeException('Resolusi gambar terlalu besar. Maksimal 8.000 px per sisi dan 25 megapiksel.');
    }

    // Tolak payload yang ditempel setelah penutup container gambar.
    $contents = @file_get_contents($tmpName);
    if ($contents === false || strlen($contents) !== $fileSize) {
        throw new RuntimeException('File gambar tidak dapat diverifikasi sepenuhnya.');
    }
    if (preg_match('/<\?(?:php|=)|<script\b|<html\b|javascript\s*:/i', $contents)) {
        throw new RuntimeException('Gambar mengandung payload aktif yang tidak diizinkan.');
    }

    $validContainerEnd = false;
    if ($detectedMime === 'image/jpeg') {
        $validContainerEnd = substr($contents, -2) === "\xFF\xD9";
    } elseif ($detectedMime === 'image/png') {
        $validContainerEnd = substr($contents, -12) === "\x00\x00\x00\x00IEND\xAE\x42\x60\x82";
    } elseif ($detectedMime === 'image/webp' && strlen($contents) >= 12) {
        $riffSize = unpack('Vsize', substr($contents, 4, 4));
        $validContainerEnd = substr($contents, 0, 4) === 'RIFF'
            && substr($contents, 8, 4) === 'WEBP'
            && isset($riffSize['size'])
            && ((int)$riffSize['size'] + 8) === $fileSize;
    }
    unset($contents);

    if (!$validContainerEnd) {
        throw new RuntimeException('Struktur akhir file gambar tidak valid atau memiliki data tambahan.');
    }

    return [
        'mime' => $detectedMime,
        'extension' => $extension,
        'width' => $width,
        'height' => $height,
        'size' => $fileSize,
    ];
}

function secure_upload_filename($prefix, $extension)
{
    $safePrefix = preg_replace('/[^a-z0-9_-]/i', '', (string)$prefix) ?: 'file';
    $safeExtension = strtolower(preg_replace('/[^a-z0-9]/i', '', (string)$extension));
    return $safePrefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(16)) . '.' . $safeExtension;
}

function json_response(array $payload, $statusCode = 200)
{
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload);
    exit;
}

function is_logged_in()
{
    return !empty($_SESSION['userdata']) && current_user_level() > 0;
}

function require_login($json = true)
{
    if (is_logged_in()) {
        return;
    }

    if ($json) {
        json_response(['status' => 'error', 'msg' => 'Unauthorized'], 401);
    }

    redirect('admin/login.php');
}

function require_admin_auth($requiredLevel = null, $json = true)
{
    require_login($json);

    if ($requiredLevel !== null && !check_role_access($requiredLevel)) {
        if ($json) {
            json_response(['status' => 'error', 'msg' => 'Forbidden'], 403);
        }
        redirect('admin/');
    }
}

function require_http_method(array $allowedMethods, $json = true)
{
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $allowedMethods = array_map('strtoupper', $allowedMethods);
    if (in_array($method, $allowedMethods, true)) {
        return;
    }

    if (!headers_sent()) {
        header('Allow: ' . implode(', ', $allowedMethods));
    }
    if ($json) {
        json_response(['status' => 'error', 'msg' => 'Method Not Allowed'], 405);
    }
    http_response_code(405);
    exit('Method Not Allowed');
}

function destroy_current_session()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => (bool)$params['secure'],
            'httponly' => (bool)$params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function request_csrf_token()
{
    return $_POST['csrf_token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? '';
}

function verify_csrf_token($token = null)
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $token = $token ?? request_csrf_token();
    return $sessionToken !== '' && is_string($token) && hash_equals($sessionToken, $token);
}

function require_csrf_for_unsafe_request($json = true)
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }

    if (verify_csrf_token()) {
        return;
    }

    if ($json) {
        json_response(['status' => 'error', 'msg' => 'Invalid CSRF token'], 403);
    }

    http_response_code(403);
    exit('Invalid CSRF token');
}

function is_direct_script($file)
{
    if (php_sapi_name() === 'cli') {
        return false;
    }

    $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
    return $script !== '' && realpath($script) === realpath($file);
}

function normalize_action($action, $default = 'none')
{
    $action = strtolower(trim((string)$action));
    return preg_match('/^[a-z0-9_\-]+$/', $action) ? $action : $default;
}

function request_action($default = 'none')
{
    return normalize_action($_GET['f'] ?? $_POST['f'] ?? $_GET['action'] ?? $_POST['action'] ?? $default, $default);
}

function validate_image($file)
{
    if (!empty($file)) {
        $ex = explode('?', $file);
        $file = str_replace('\\', '/', ltrim($ex[0], '/'));
        $param =  isset($ex[1]) ? '?' . $ex[1]  : '';
        if (preg_match('#(^|/)\.\.(/|$)#', $file)) {
            return base_url . 'uploads/no-image-available.png';
        }
        if (is_file(base_app . $file)) {
            return base_url . $file . $param;
        } else {
            return base_url . 'uploads/no-image-available.png';
        }
    } else {
        return base_url . 'uploads/no-image-available.png';
    }
}

function get_user_avatar($avatar_filename)
{
    if (empty($avatar_filename)) {
        return base_url . 'assets/media/avatars/blank.png';
    }
    $avatar_filename = basename(str_replace('\\', '/', $avatar_filename));
    $avatar_path = base_app . 'uploads/' . $avatar_filename;
    return file_exists($avatar_path)
        ? base_url . 'uploads/' . $avatar_filename
        : base_url . 'assets/media/avatars/blank.png';
}

function isMobileDevice()
{
    $aMobileUA = [
        '/iphone/i',
        '/ipod/i',
        '/ipad/i',
        '/android/i',
        '/blackberry/i',
        '/webos/i'
    ];
    foreach ($aMobileUA as $regex) {
        if (preg_match($regex, $_SERVER['HTTP_USER_AGENT'])) return true;
    }
    return false;
}

function namaBulan($angka)
{
    $bulan = [
        1 => "Januari",
        2 => "Februari",
        3 => "Maret",
        4 => "April",
        5 => "Mei",
        6 => "Juni",
        7 => "Juli",
        8 => "Agustus",
        9 => "September",
        10 => "Oktober",
        11 => "November",
        12 => "Desember"
    ];
    return $bulan[intval($angka)] ?? '-';
}

function tglIndonesia($tgl)
{
    if (!$tgl) return '-';
    $tanggal = substr($tgl, 8, 2);
    $bulan = namaBulan(substr($tgl, 5, 2));
    $tahun = substr($tgl, 0, 4);
    return "$tanggal $bulan $tahun";
}

function hariIndonesia($tanggal)
{
    $hariKe = date('N', strtotime($tanggal));
    $namaHari = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu'
    ];
    return $namaHari[$hariKe] ?? '';
}

function greetingtime()
{
    $jam = date("H");
    if ($jam < 10) return "Selamat Pagi,";
    elseif ($jam < 15) return "Selamat Siang,";
    elseif ($jam < 18) return "Selamat Sore,";
    else return "Selamat Malam,";
}

/**
 * 🔐 Helper function untuk check role access
 * @param int|array $requiredLevel - Level ID atau array of level IDs yang diizinkan
 * @return bool - true jika user memiliki akses
 */
function check_role_access($requiredLevel = [])
{
    // Jika tidak login, return false
    if (current_user_level() <= 0) {
        return false;
    }

    // Jika requiredLevel string "all", allow semua role
    if ($requiredLevel === "all") {
        return true;
    }

    // Convert ke array jika single value
    if (!is_array($requiredLevel)) {
        $requiredLevel = [$requiredLevel];
    }

    // Check jika level user ada di array requiredLevel  
    return in_array(current_user_level(), array_map('intval', $requiredLevel), true);
}

/**
 * 🎯 Get user role name dari level_id
 * @param int $levelId - Level ID
 * @return string - Nama level atau "Unknown"
 */
function get_role_name($levelId = null)
{
    if ($levelId === null) {
        $levelId = current_user_level();
    }

    $roles = [
        1 => 'Admin',
        2 => 'Kasir',
        3 => 'Salesman',
        4 => 'Supervisor'
    ];

    return $roles[$levelId] ?? 'Unknown';
}

function current_user_level()
{
    return (int)($_SESSION['userdata']['level_id'] ?? $_SESSION['level_id'] ?? 0);
}

function normalize_admin_page($page)
{
    $page = trim((string)$page);
    $page = str_replace(['..', './', '\\'], ['', '', '/'], $page);
    $page = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $page);
    $page = trim($page, '/');
    return $page !== '' ? $page : 'home';
}

function default_admin_page_for_role($levelId = null)
{
    $levelId = $levelId === null ? current_user_level() : (int)$levelId;
    if ($levelId === 3) {
        return 'penjualan';
    }
    return 'home';
}

function can_access_admin_page($page, $levelId = null)
{
    $levelId = $levelId === null ? current_user_level() : (int)$levelId;
    $page = normalize_admin_page($page);

    if ($levelId === 1 || $levelId === 4) {
        return true;
    }

    $allowed = [
        2 => [
            'home',
            'penjualan/kasir',
        ],
        3 => [
            'home',
            'penjualan',
        ],
    ];

    return in_array($page, $allowed[$levelId] ?? [], true);
}

// Jangan close buffer di sini - biarkan file yang include ini yang manage buffer
