<?php
/**
 * Konfigurasi Database PDO - DAPUR TYAS by TECNO GARAGE
 * Menggunakan PDO Prepared Statements & Error Handling
 * Mendukung Environment Variables (.env / Hosting Server) & Fallback Default
 */

$dbHost = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost');
$dbName = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'dapur_tyas');
$dbUser = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'root');
$dbPass = getenv('DB_PASS');
if ($dbPass === false) {
    $dbPass = $_ENV['DB_PASS'] ?? '';
}
$dbCharset = getenv('DB_CHARSET') ?: ($_ENV['DB_CHARSET'] ?? 'utf8mb4');

defined('DB_HOST') or define('DB_HOST', $dbHost);
defined('DB_NAME') or define('DB_NAME', $dbName);
defined('DB_USER') or define('DB_USER', $dbUser);
defined('DB_PASS') or define('DB_PASS', $dbPass);
defined('DB_CHARSET') or define('DB_CHARSET', $dbCharset);

class Database {
    private static ?PDO $instance = null;

    /**
     * Mendapatkan instance koneksi PDO tunggal (Singleton)
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Log error internal tanpa mengekspos kredensial ke publik
                error_log("Database Connection Error: " . $e->getMessage());
                
                // Tampilan error ramah pengguna
                if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                    die("Koneksi Database Gagal: " . htmlspecialchars($e->getMessage()));
                } else {
                    die("Terjadi gangguan koneksi sistem. Silakan hubungi administrator.");
                }
            }
        }

        return self::$instance;
    }
}

/**
 * Helper function singkat untuk mendapatkan koneksi PDO
 */
function db(): PDO {
    return Database::getConnection();
}
