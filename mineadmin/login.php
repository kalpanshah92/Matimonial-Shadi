<?php
// F-08/F-14 Hardened session bootstrap (must happen before session_start)
require_once __DIR__ . '/../includes/functions.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // F-16 CSRF
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission.';
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$error && (empty($username) || empty($password))) {
        $error = 'Please enter username and password.';
    }

    // F-16 Lockout + rate limit
    if (!$error && isLoginLocked($username, 'admin')) {
        $error = 'Too many failed attempts. Please try again in 15 minutes.';
    }
    if (!$error && !rateLimit('adminlogin:ip:' . clientIp(), 10, 900)) {
        $error = 'Too many login attempts. Please try again later.';
    }

    if (!$error) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE (username = ? OR email = ?) AND is_active = 1");
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            // F-08 Regenerate session on privilege escalation
            session_regenerate_id(true);
            $_SESSION['admin_id']   = (int)$admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_role'] = $admin['role'];

            recordLoginAttempt($username, true, 'admin');
            $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);

            header('Location: index.php');
            exit;
        } else {
            recordLoginAttempt($username, false, 'admin');
            // F-11 Generic error
            $error = 'Invalid credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-warm">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-5 col-lg-4">
                <div class="auth-card">
                    <div class="auth-header text-center">
                        <h2><i class="bi bi-shield-lock"></i> Admin Login</h2>
                        <p><?= SITE_NAME ?> Administration</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <div class="mb-3">
                            <label class="form-label">Username or Email</label>
                            <input type="text" class="form-control" name="username" required autofocus>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Login
                        </button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <a href="<?= SITE_URL ?>"><i class="bi bi-arrow-left me-1"></i>Back to Website</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
