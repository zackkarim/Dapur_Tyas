<?php
/**
 * Helper Functions - DAPUR TYAS by TECNO GARAGE
 * Kumpulan fungsi reusable untuk formatting, sanitasi, token, local URL & response
 */

/**
 * Mendapatkan Base URL Server / Domain (untuk QR Code & Akses Pelanggan)
 * Mengutamakan nilai dari tabel settings `local_base_url` (jika valid),
 * atau otomatis menggunakan BASE_URL saat di hosting / domain publik.
 */
function getLocalBaseUrl(): string {
    $dbVal = trim(getSetting('local_base_url', ''));
    $currentBaseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

    // Cek apakah host saat ini adalah domain publik / live hosting (bukan localhost / ip private)
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $isLiveHost = !empty($host) && !preg_match('/^(localhost|127\.0\.0\.1|192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/i', $host);

    if (!empty($dbVal)) {
        $dbVal = rtrim($dbVal, '/');
        // Jika sedang berjalan di live hosting, tapi nilai DB masih berupa IP lokal/localhost lama,
        // prioritaskan URL domain hosting saat ini agar QR code tidak mengarah ke IP lokal laptop lama
        $dbIsLocal = preg_match('#https?://(localhost|127\.0\.0\.1|192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)#i', $dbVal);
        if ($isLiveHost && $dbIsLocal && !empty($currentBaseUrl)) {
            return $currentBaseUrl;
        }
        return $dbVal;
    }

    if (!empty($currentBaseUrl)) {
        return $currentBaseUrl;
    }

    if (defined('LOCAL_BASE_URL_DEFAULT') && !empty(LOCAL_BASE_URL_DEFAULT)) {
        return rtrim(LOCAL_BASE_URL_DEFAULT, '/');
    }

    return 'http://localhost/dapur_tyas';
}

function getCustomerGeneralUrl(): string {
    return getLocalBaseUrl() . '/customer/';
}

/**
 * Generate URL Pemesanan Meja untuk Customer / QR Code
 * Menggunakan Base URL + /customer/?meja=XX
 * Contoh: "Meja 05" -> "https://namadomain.com/customer/?meja=05"
 */
function getCustomerTableUrl(string|int $nomorMejaOrId): string {
    $raw = trim((string)$nomorMejaOrId);
    $cleanNo = preg_replace('/^Meja\s*/i', '', $raw);
    if (is_numeric($cleanNo)) {
        $cleanNo = sprintf('%02d', (int)$cleanNo);
    }
    return getLocalBaseUrl() . '/customer/?meja=' . urlencode($cleanNo);
}

/**
 * Deteksi daftar IP / Domain yang tersedia untuk konfigurasi akses pelanggan
 */
function getDetectedLocalIps(): array {
    $ips = [];
    
    // Server IP jika diakses via IP
    if (!empty($_SERVER['SERVER_ADDR']) && filter_var($_SERVER['SERVER_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false && $_SERVER['SERVER_ADDR'] !== '127.0.0.1') {
        $ips[] = $_SERVER['SERVER_ADDR'];
    }

    // Windows ipconfig parsing (hanya jika fungsi diizinkan dan berjalan di Windows)
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && function_exists('shell_exec')) {
        $output = @shell_exec('ipconfig');
        if ($output) {
            preg_match_all('/IPv4 Address[ .]*:[ ]*([0-9\.]+)/i', $output, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $ip) {
                    if ($ip !== '127.0.0.1' && !in_array($ip, $ips, true)) {
                        $ips[] = $ip;
                    }
                }
            }
        }
    }

    return array_unique($ips);
}

/**
 * Format angka ke format mata uang Rupiah
 * Contoh: 25000 -> "Rp 25.000"
 */
function formatRupiah(float|int|string $nominal, bool $withPrefix = true): string {
    $cleanNum = (float) $nominal;
    $formatted = number_format($cleanNum, 0, ',', '.');
    return $withPrefix ? 'Rp ' . $formatted : $formatted;
}

/**
 * Format tanggal dan waktu ke standar bahasa Indonesia
 * Contoh: "2026-09-22 14:30:00" -> "22 September 2026, 14:30 WIB"
 */
function formatTanggalIndo(?string $datetime, bool $withTime = true): string {
    if (empty($datetime)) {
        return '-';
    }

    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    $hari = [
        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
    ];

    $timestamp = strtotime($datetime);
    if (!$timestamp) {
        return $datetime;
    }

    $tgl = (int) date('d', $timestamp);
    $bln = (int) date('m', $timestamp);
    $thn = date('Y', $timestamp);
    $namaBulan = $bulan[$bln] ?? '';

    $hasil = "$tgl $namaBulan $thn";

    if ($withTime) {
        $jam = date('H:i', $timestamp);
        $hasil .= ", $jam WIB";
    }

    return $hasil;
}

/**
 * Generate nomor pesanan unik
 * Contoh: DT-20260922-8492
 */
function generateNomorOrder(): string {
    $prefix = 'DT';
    $date = date('Ymd');
    $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
    return "{$prefix}-{$date}-{$random}";
}

/**
 * Generate nomor transaksi unik untuk pembayaran
 * Contoh: TRX-20260922-9214
 */
function generateNomorTransaksi(): string {
    $prefix = 'TRX';
    $date = date('Ymd');
    $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
    return "{$prefix}-{$date}-{$random}";
}

/**
 * Generate token acak untuk customer session / QR identifier
 */
function generateToken(int $length = 32): string {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Sanitasi input pengguna untuk mencegah XSS & injeksi karakter
 */
function sanitize(mixed $data): mixed {
    if (is_array($data)) {
        foreach ($data as $key => $val) {
            $data[$key] = sanitize($val);
        }
        return $data;
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

/**
 * Helper response format JSON standar API
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Helper redirect URL
 */
function redirect(string $url): void {
    if (!filter_var($url, FILTER_VALIDATE_URL) && !str_starts_with($url, '/')) {
        $url = BASE_URL . '/' . ltrim($url, '/');
    }

    if (ob_get_length() > 0) {
        ob_clean();
    }

    if (!headers_sent()) {
        header("Location: " . $url);
        exit;
    }

    echo '<script type="text/javascript">window.location.href = ' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
}

/**
 * Set flash message ke session
 */
function setFlash(string $key, string $message, string $type = 'success'): void {
    $_SESSION['flash'][$key] = [
        'message' => $message,
        'type'    => $type
    ];
}

/**
 * Ambil dan hapus flash message dari session
 */
function getFlash(string $key): ?array {
    if (isset($_SESSION['flash'][$key])) {
        $flash = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $flash;
    }
    return null;
}

/**
 * Cek ketersediaan flash message
 */
function hasFlash(string $key): bool {
    return isset($_SESSION['flash'][$key]);
}

/**
 * CSRF Protection - Dapatkan / Generate CSRF Token
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF Protection - Output input field hidden
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * CSRF Protection - Verifikasi CSRF Token
 */
function verify_csrf(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Mengambil nilai setting dari tabel database `settings`
 */
function getSetting(string $key, string $default = ''): string {
    static $cache = [];
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    try {
        $stmt = db()->prepare("SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1");
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch();
        $val = $row ? (string)$row['setting_value'] : $default;
        $cache[$key] = $val;
        return $val;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Menyimpan / memperbarui nilai setting di tabel `settings`
 */
function setSetting(string $key, string $value): bool {
    try {
        $stmt = db()->prepare("INSERT INTO settings (setting_key, setting_value) 
                               VALUES (:key, :val) 
                               ON DUPLICATE KEY UPDATE setting_value = :val_update");
        return $stmt->execute([
            'key'        => $key,
            'val'        => $value,
            'val_update' => $value
        ]);
    } catch (Exception $e) {
        error_log("setSetting Error: " . $e->getMessage());
        return false;
    }
}
