<?php
/**
 * User Login Page
 * AI Camera POS System
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/auth.php';

// If already logged in, redirect based on role
if (is_logged_in()) {
    if (is_admin()) {
        header('Location: ' . base_url('admin/index.php'));
    } else {
        header('Location: ' . base_url('pos/index.php'));
    }
    exit;
}

$error = '';

// Handle Login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            try {
                $pdo = get_db_connection();
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u AND status = 'active' LIMIT 1");
                $stmt->execute(['u' => $username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Password correct, regenerate session ID to prevent fixation
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_username'] = $user['username'];
                    $_SESSION['user_fullname'] = $user['full_name'];
                    $_SESSION['user_role'] = $user['role'];

                    log_audit('LOGIN', "User {$user['username']} logged in successfully");

                    set_flash('success', "Welcome back, {$user['full_name']}!");

                    if ($user['role'] === 'admin') {
                        header('Location: ' . base_url('admin/index.php'));
                    } else {
                        header('Location: ' . base_url('pos/index.php'));
                    }
                    exit;
                } else {
                    $error = 'Invalid username or password, or account is inactive.';
                    log_audit('LOGIN_FAILED', "Failed login attempt for username: {$username}");
                }
            } catch (Exception $e) {
                $error = 'Database connection error: ' . $e->getMessage();
            }
        }
    }
}

$shopName = get_setting('shop_name', 'AI SMART MART');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= clean($shopName) ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .login-card {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: 1.25rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .login-header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            padding: 2.25rem 2rem 1.75rem;
            text-align: center;
        }
        .login-header .brand-icon {
            width: 64px;
            height: 64px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 0.75rem;
            backdrop-filter: blur(4px);
        }
        .login-body {
            padding: 2rem;
        }
        .demo-btn {
            font-size: 0.85rem;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="brand-icon">
            <i class="bi bi-camera-fill"></i>
        </div>
        <h4 class="fw-bold mb-1"><?= clean($shopName) ?></h4>
        <p class="mb-0 text-white-50 small">AI Vision Scan Point-of-Sale System</p>
    </div>

    <div class="login-body">
        <?php $flash = get_flash(); ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?= clean($flash['type']) ?> alert-dismissible fade show" role="alert">
                <?= clean($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= clean($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= base_url('login.php') ?>" id="loginForm">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="username" class="form-label fw-semibold text-secondary small">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Enter username" required autofocus autocomplete="username">
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label fw-semibold text-secondary small">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required autocomplete="current-password">
                </div>
            </div>

            <div class="d-grid mb-4">
                <button type="submit" class="btn btn-primary btn-lg fw-semibold shadow-sm">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                </button>
            </div>
        </form>

        <div class="border-top pt-3 text-center">
            <p class="text-muted small mb-2 fw-medium">Quick Demo One-Click Login:</p>
            <div class="d-flex gap-2 justify-content-center">
                <button type="button" class="btn btn-outline-primary demo-btn flex-fill" onclick="quickLogin('admin', 'admin123')">
                    <i class="bi bi-shield-lock me-1"></i> Admin
                </button>
                <button type="button" class="btn btn-outline-success demo-btn flex-fill" onclick="quickLogin('cashier', 'cashier123')">
                    <i class="bi bi-cart me-1"></i> Cashier
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function quickLogin(user, pass) {
    document.getElementById('username').value = user;
    document.getElementById('password').value = pass;
    document.getElementById('loginForm').submit();
}
</script>
</body>
</html>
