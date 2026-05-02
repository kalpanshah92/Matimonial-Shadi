<?php
$pageTitle = 'Login';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect(SITE_URL . '/dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    }
    
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email)) $errors[] = 'Email is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (empty($password)) $errors[] = 'Password is required.';
    
    if (empty($errors)) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Check if account is approved by admin
            if ($user['status'] === 'pending') {
                $errors[] = 'Your account is pending approval. Please wait for admin to approve your registration.';
            } elseif ($user['status'] === 'rejected') {
                $errors[] = 'Your account has been rejected. Please contact support for more information.';
            } elseif ($user['status'] === 'suspended') {
                $errors[] = 'Your account has been suspended. Please contact support.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                
                // Update last login
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // Handle remember me
                if (isset($_POST['remember_me'])) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
                }
                
                $redirect = $_GET['redirect'] ?? SITE_URL . '/dashboard.php';
                setFlash('success', 'Welcome back, ' . $user['name'] . '!');
                redirect($redirect);
            }
        } else {
            $errors[] = 'Invalid email or password.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Login Page -->
<section class="auth-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="auth-card">
                    <div class="auth-header text-center">
                        <h2><i class="bi bi-hearts"></i> Welcome Back</h2>
                        <p>Login to find your perfect match</p>
                    </div>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= $error ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="loginForm">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= sanitize($_POST['email'] ?? '') ?>" required 
                                       placeholder="Enter email address">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required placeholder="Enter password">
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
                                <label class="form-check-label" for="remember_me">Remember me</label>
                            </div>
                            <a href="<?= SITE_URL ?>/forgot-password.php" class="text-primary">Forgot Password?</a>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Login
                        </button>
                        
                        <div class="text-center mt-4">
                            <p>Don't have an account? <a href="<?= SITE_URL ?>/register.php" class="fw-semibold">Register Free</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
