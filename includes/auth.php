<?php
/**
 * Authentication & Role Middleware - DAPUR TYAS by TECNO GARAGE
 * Mengelola otentikasi session, password_verify(), dan pembatasan role
 */

/**
 * Cek apakah user telah login
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Mendapatkan data user yang sedang login
 */
function getAuthUser(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Mendapatkan role user yang sedang login ('admin' | 'kasir')
 */
function getAuthRole(): ?string {
    return $_SESSION['user']['role'] ?? null;
}

/**
 * Middleware: Wajib login dengan filter role
 */
function requireLogin(array $allowedRoles = []): void {
    if (!isLoggedIn()) {
        setFlash('error', 'Silakan login terlebih dahulu untuk mengakses halaman ini.', 'danger');
        redirect('auth/login.php');
    }

    if (!empty($allowedRoles)) {
        $currentRole = getAuthRole();
        if (!in_array($currentRole, $allowedRoles, true)) {
            // Jika role tidak sesuai
            setFlash('error', 'Anda tidak memiliki hak akses ke halaman tersebut.', 'danger');
            
            if ($currentRole === 'admin') {
                redirect('admin/index.php');
            } elseif ($currentRole === 'kasir') {
                redirect('kasir/index.php');
            } else {
                redirect('auth/login.php');
            }
        }
    }
}

/**
 * Middleware khusus Admin
 */
function requireAdmin(): void {
    requireLogin(['admin']);
}

/**
 * Middleware khusus Kasir atau Admin
 */
function requireKasir(): void {
    requireLogin(['kasir', 'admin']);
}

/**
 * Proses autentikasi user (Login)
 */
function loginUser(string $username, string $password): array {
    $cleanUsername = trim($username);

    if (empty($cleanUsername) || empty($password)) {
        return [
            'success' => false,
            'message' => 'Username dan password wajib diisi.'
        ];
    }

    try {
        $stmt = db()->prepare("SELECT * FROM users WHERE username = :username AND status = 'aktif' LIMIT 1");
        $stmt->execute(['username' => $cleanUsername]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID untuk mencegah session fixation attack
            if (!headers_sent()) {
                session_regenerate_id(true);
            }

            // Simpan data aman ke session
            $_SESSION['user'] = [
                'id'       => (int) $user['id'],
                'nama'     => $user['nama'],
                'username' => $user['username'],
                'role'     => $user['role'],
                'login_at' => date('Y-m-d H:i:s')
            ];

            return [
                'success' => true,
                'role'    => $user['role'],
                'message' => 'Login berhasil. Selamat datang, ' . $user['nama'] . '!'
            ];
        }

        return [
            'success' => false,
            'message' => 'Username atau password salah, atau akun dinonaktifkan.'
        ];
    } catch (PDOException $e) {
        error_log("Login Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Terjadi kesalahan sistem saat memproses login.'
        ];
    }
}

/**
 * Proses logout user
 */
function logoutUser(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}
