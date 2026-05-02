<?php
$pageTitle = 'Register';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect(SITE_URL . '/dashboard.php');
}

$errors = [];
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    }
    
    // Collect and sanitize input
    $formData = [
        'name'       => sanitize($_POST['name'] ?? ''),
        'email'      => sanitize($_POST['email'] ?? ''),
        'phone'      => sanitize($_POST['phone'] ?? ''),
        'password'   => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'gender'     => sanitize($_POST['gender'] ?? ''),
        'dob'        => sanitize($_POST['dob'] ?? ''),
        'religion'   => sanitize($_POST['religion'] ?? ''),
        'caste'      => sanitize($_POST['caste'] ?? ''),
        'mother_tongue' => sanitize($_POST['mother_tongue'] ?? ''),
        'state'      => sanitize($_POST['state'] ?? ''),
        'city'       => sanitize($_POST['city'] ?? ''),
        'profile_for' => sanitize($_POST['profile_for'] ?? ''),
    ];
    
    // Validation
    if (empty($formData['name'])) $errors[] = 'Name is required.';
    if (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (empty($formData['phone']) || !preg_match('/^[6-9]\d{9}$/', $formData['phone'])) $errors[] = 'Valid Indian mobile number is required.';
    if (strlen($formData['password']) < 8) $errors[] = 'Password must be at least 8 characters long.';
    if (!preg_match('/[0-9]/', $formData['password'])) $errors[] = 'Password must contain at least 1 number.';
    if (empty($_POST['terms'])) $errors[] = 'You must agree to the Terms of Service and Privacy Policy.';
    if ($formData['password'] !== $formData['confirm_password']) $errors[] = 'Passwords do not match.';
    if (empty($formData['gender'])) $errors[] = 'Gender is required.';
    if (empty($formData['dob'])) $errors[] = 'Date of birth is required.';
    
    // Age validation (18+)
    if (!empty($formData['dob'])) {
        $age = calculateAge($formData['dob']);
        if ($age < 18) $errors[] = 'You must be at least 18 years old.';
        if ($age > 80) $errors[] = 'Please enter a valid date of birth.';
    }
    
    // Check if email/phone already exists
    if (empty($errors)) {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$formData['email']]);
        if ($stmt->fetch()) $errors[] = 'Email is already registered.';
        
        // Phone number can be shared across profiles (family registrations)
    }
    
    // Register user
    if (empty($errors)) {
        try {
            $pdo = getDBConnection();
            $profileId = generateProfileId($formData['gender']);
            $hashedPassword = password_hash($formData['password'], PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare(
                "INSERT INTO users (profile_id, name, email, phone, password, gender, dob, religion, caste, mother_tongue, state, city, status) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
            );
            
            $stmt->execute([
                $profileId,
                $formData['name'],
                $formData['email'],
                $formData['phone'],
                $hashedPassword,
                $formData['gender'],
                $formData['dob'],
                $formData['religion'],
                $formData['caste'],
                $formData['mother_tongue'],
                $formData['state'],
                $formData['city']
            ]);
            
            $userId = $pdo->lastInsertId();
            
            // Create empty profile records
            $pdo->prepare("INSERT INTO profile_details (user_id) VALUES (?)")->execute([$userId]);
            $pdo->prepare("INSERT INTO family_details (user_id) VALUES (?)")->execute([$userId]);
            $pdo->prepare("INSERT INTO partner_preferences (user_id) VALUES (?)")->execute([$userId]);
            $pdo->prepare("INSERT INTO privacy_settings (user_id) VALUES (?)")->execute([$userId]);
            
            setFlash('success', 'Registration successful! Your account is pending approval by admin. You will be able to login once approved.');
            redirect(SITE_URL . '/login.php');
            
        } catch (PDOException $e) {
            error_log("Registration Error: " . $e->getMessage());
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Registration Page -->
<section class="auth-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="auth-card">
                    <div class="auth-header text-center">
                        <h2><i class="bi bi-hearts"></i> Create Your Account</h2>
                        <p>Find your perfect life partner on <?= SITE_NAME ?></p>
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
                    
                    <form method="POST" action="" id="registerForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <!-- Profile For -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">This profile is for <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                <?php 
                                $profileTypes = ['Myself', 'Son', 'Daughter', 'Brother', 'Sister', 'Relative', 'Friend'];
                                foreach ($profileTypes as $type): 
                                ?>
                                    <div class="col-auto">
                                        <input type="radio" class="btn-check" name="profile_for" id="pf_<?= strtolower($type) ?>" 
                                               value="<?= $type ?>" <?= ($formData['profile_for'] ?? '') === $type ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-primary btn-sm" for="pf_<?= strtolower($type) ?>"><?= $type ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="row g-3">
                            <!-- Name -->
                            <div class="col-md-6">
                                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?= $formData['name'] ?? '' ?>" required placeholder="Enter full name">
                            </div>
                            
                            <!-- Gender -->
                            <div class="col-md-6">
                                <label class="form-label">Gender <span class="text-danger">*</span></label>
                                <div class="d-flex gap-3 mt-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="gender" id="male" value="Male"
                                               <?= ($formData['gender'] ?? '') === 'Male' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="male"><i class="bi bi-gender-male me-1"></i>Male</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="gender" id="female" value="Female"
                                               <?= ($formData['gender'] ?? '') === 'Female' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="female"><i class="bi bi-gender-female me-1"></i>Female</label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Email -->
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= $formData['email'] ?? '' ?>" required placeholder="your@email.com">
                            </div>
                            
                            <!-- Phone -->
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">+91</span>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?= $formData['phone'] ?? '' ?>" required placeholder="10-digit number" maxlength="10">
                                </div>
                            </div>
                            
                            <!-- Date of Birth -->
                            <div class="col-md-6">
                                <label for="dob" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="dob" name="dob" 
                                       value="<?= $formData['dob'] ?? '' ?>" required max="<?= date('Y-m-d', strtotime('-18 years')) ?>">
                            </div>
                            
                            <!-- Religion -->
                            <div class="col-md-6">
                                <label for="religion" class="form-label">Religion</label>
                                <select class="form-select" id="religion" name="religion">
                                    <option value="">Select Religion</option>
                                    <?php foreach ($RELIGIONS as $r): ?>
                                        <option value="<?= $r ?>" <?= ($formData['religion'] ?? '') === $r ? 'selected' : '' ?>><?= $r ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Caste -->
                            <div class="col-md-6">
                                <label for="caste" class="form-label">Samaj Name</label>
                                <input type="text" class="form-control" id="caste" name="caste" 
                                       value="<?= $formData['caste'] ?? '' ?>" placeholder="Enter Samaj Name">
                            </div>
                            
                            <!-- Mother Tongue -->
                            <div class="col-md-6">
                                <label for="mother_tongue" class="form-label">Mother Tongue</label>
                                <select class="form-select" id="mother_tongue" name="mother_tongue">
                                    <option value="">Select Language</option>
                                    <?php foreach ($MOTHER_TONGUES as $lang): ?>
                                        <option value="<?= $lang ?>" <?= ($formData['mother_tongue'] ?? '') === $lang ? 'selected' : '' ?>><?= $lang ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- State -->
                            <div class="col-md-6">
                                <label for="state" class="form-label">State</label>
                                <select class="form-select" id="state" name="state">
                                    <option value="">Select State</option>
                                    <?php foreach ($INDIAN_STATES as $state): ?>
                                        <option value="<?= $state ?>" <?= ($formData['state'] ?? '') === $state ? 'selected' : '' ?>><?= $state ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- City -->
                            <div class="col-md-6">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city" 
                                       value="<?= $formData['city'] ?? '' ?>" placeholder="Enter city">
                            </div>
                            
                            <!-- Password -->
                            <div class="col-md-6">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" 
                                           required minlength="8" placeholder="Min 8 characters, include a number">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Confirm Password -->
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           required placeholder="Re-enter password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Terms -->
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="#" target="_blank">Terms of Service</a> and <a href="#" target="_blank">Privacy Policy</a>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg w-100 mt-4">
                            <i class="bi bi-person-plus me-2"></i>Register Free
                        </button>
                        
                        <div class="text-center mt-3">
                            <p>Already have an account? <a href="<?= SITE_URL ?>/login.php" class="fw-semibold">Login here</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
