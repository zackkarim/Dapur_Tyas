<?php
/**
 * Login Authentication Portal - DAPUR TYAS by TECNO GARAGE
 */

require_once dirname(__DIR__) . '/config/app.php';

// Jika sudah login, langsung arahkan ke dashboard masing-masing
if (isLoggedIn()) {
    $role = getAuthRole();
    if ($role === 'admin') {
        redirect('admin/index.php');
    } else {
        redirect('kasir/index.php');
    }
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $error = 'Sesi keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $login = loginUser($username, $password);
        if ($login['success']) {
            if ($login['role'] === 'admin') {
                redirect('admin/index.php');
            } else {
                redirect('kasir/index.php');
            }
        } else {
            $error = $login['message'];
        }
    }
}

$flashError = getFlash('error');
if ($flashError) {
    $error = $flashError['message'];
}

$restoName = getSetting('nama_restoran', 'DAPUR TYAS');
$restoTag  = getSetting('tagline', 'MASAKAN RUMAHAN YANG LEZAT');
$restoSub  = getSetting('sub_brand', 'by TECNO GARAGE');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Staff — <?= htmlspecialchars($restoName) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            background:
                linear-gradient(135deg, rgba(23, 26, 32, 0.82) 0%, rgba(36, 40, 49, 0.72) 50%, rgba(58, 47, 37, 0.78) 100%),
                url('<?= BASE_URL ?>/assets/images/bg/login-bg.jpg') center / cover no-repeat fixed;
            overflow: hidden;
        }

        .login-wrapper::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(255, 255, 255, 0.045) 1.2px, transparent 1.2px);
            background-size: 26px 26px;
            pointer-events: none;
        }

        .login-wrapper::after {
            content: "";
            position: absolute;
            top: -120px;
            right: -120px;
            width: 420px;
            height: 420px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(200, 90, 50, 0.22) 0%, transparent 68%);
            pointer-events: none;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: #FFFFFF;
            border-radius: var(--radius-xl);
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.45);
            border: 1px solid rgba(255, 255, 255, 0.35);
            padding: 36px 30px;
            position: relative;
            z-index: 2;
        }

        .login-brand {
            text-align: center;
            margin-bottom: 26px;
        }

        .login-brand-logo-frame {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            background: #FAF7F2;
            border: 3px solid var(--border-color);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 12px;
            box-shadow: var(--shadow-sm);
        }

        .login-brand-logo-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .brand-title {
            font-size: 1.45rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .brand-subtitle {
            font-size: 0.76rem;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .input-group-custom {
            position: relative;
        }

        .input-group-custom i.input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 1rem;
        }

        .input-group-custom .form-control {
            padding-left: 42px;
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-brand">
            <div class="login-brand-logo-frame">
                <img src="<?= BASE_URL ?>/promt/tambah_logo.png" alt="Logo">
            </div>
            <h1 class="brand-title"><?= htmlspecialchars($restoName) ?></h1>
            <div class="brand-subtitle"><?= htmlspecialchars($restoTag) ?></div>
            <div style="font-size: 0.72rem; color: var(--text-light); margin-top: 2px;"><?= htmlspecialchars($restoSub) ?></div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/auth/login.php" method="POST" autocomplete="off">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <div class="input-group-custom">
                    <i class="fa-solid fa-user input-icon"></i>
                    <input type="text" id="username" name="username" class="form-control" 
                           placeholder="Masukkan username" value="<?= htmlspecialchars($username) ?>" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Kata Sandi</label>
                <div class="input-group-custom input-password-wrap">
                    <i class="fa-solid fa-lock input-icon"></i>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Masukkan kata sandi" required>
                    <button type="button" class="toggle-password" id="btnTogglePassword" tabindex="-1" title="Tampilkan/Sembunyikan Sandi">
                        <i class="fa-regular fa-eye" id="iconToggle"></i>
                    </button>
                </div>
            </div>

            <div style="margin-top: 24px;">
                <button type="submit" class="btn btn-primary btn-block" style="padding: 12px;">
                    <i class="fa-solid fa-right-to-bracket"></i> Masuk ke Sistem
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const btnToggle = document.getElementById('btnTogglePassword');
    const inputPass = document.getElementById('password');
    const iconToggle = document.getElementById('iconToggle');

    btnToggle.addEventListener('click', function() {
        if (inputPass.type === 'password') {
            inputPass.type = 'text';
            iconToggle.classList.remove('fa-eye');
            iconToggle.classList.add('fa-eye-slash');
        } else {
            inputPass.type = 'password';
            iconToggle.classList.remove('fa-eye-slash');
            iconToggle.classList.add('fa-eye');
        }
    });
</script>

</body>
</html>
