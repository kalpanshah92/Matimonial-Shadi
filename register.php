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
    $countryCode = sanitize($_POST['country_code'] ?? '+91');
    // Normalize country code (strip "-us", "-ca" suffixes for storage)
    $cleanCountryCode = preg_replace('/-.*$/', '', $countryCode);
    $rawPhone = sanitize($_POST['phone'] ?? '');
    $formData = [
        'name'       => sanitize($_POST['name'] ?? ''),
        'email'      => sanitize($_POST['email'] ?? ''),
        'country_code' => $countryCode,
        'phone'      => $rawPhone ? $cleanCountryCode . ' ' . $rawPhone : '',
        'password'   => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'gender'     => sanitize($_POST['gender'] ?? ''),
        'dob'        => sanitize($_POST['dob'] ?? ''),
        'religion'   => sanitize($_POST['religion'] ?? ''),
        'caste'      => sanitize($_POST['caste'] ?? ''),
        'mother_tongue' => sanitize(($_POST['mother_tongue'] ?? '') === 'Others' ? ($_POST['mother_tongue_other'] ?? '') : ($_POST['mother_tongue'] ?? '')),
        'country'    => sanitize($_POST['country'] ?? ''),
        'state'      => sanitize($_POST['state'] ?? ''),
        'city'       => sanitize($_POST['city'] ?? ''),
        'profile_for' => sanitize($_POST['profile_for'] ?? ''),
    ];
    
    // Validation
    if (empty($formData['name'])) $errors[] = 'Name is required.';
    if (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (empty($rawPhone) || !preg_match('/^[0-9]+$/', $rawPhone)) $errors[] = 'Valid mobile number is required.';
    if (strlen($formData['password']) < 8) $errors[] = 'Password must be at least 8 characters long.';
    if (!preg_match('/[0-9]/', $formData['password'])) $errors[] = 'Password must contain at least 1 number.';
    if (!preg_match('/[a-zA-Z]/', $formData['password'])) $errors[] = 'Password must contain at least 1 letter.';
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $formData['password'])) $errors[] = 'Password must contain at least 1 special character.';
    if (empty($_POST['terms'])) $errors[] = 'You must agree to the Terms of Service and Privacy Policy.';
    if ($formData['password'] !== $formData['confirm_password']) $errors[] = 'Passwords do not match.';
    if (empty($formData['gender'])) $errors[] = 'Gender is required.';
    if (empty($formData['dob'])) $errors[] = 'Date of birth is required.';
    if (($_POST['mother_tongue'] ?? '') === 'Others' && empty(trim($_POST['mother_tongue_other'] ?? ''))) {
        $errors[] = 'Please specify your mother tongue.';
    }
    
    // Age validation based on gender (Female: 18+, Male: 21+)
    if (!empty($formData['dob']) && !empty($formData['gender'])) {
        $age = calculateAge($formData['dob']);
        $minAge = ($formData['gender'] === 'Female') ? 18 : 21;
        if ($age < $minAge) $errors[] = ($formData['gender'] === 'Female') ? 'Females must be at least 18 years old.' : 'Males must be at least 21 years old.';
        if ($age > 80) $errors[] = 'Please enter a valid date of birth.';
    }
    
    // Validate profile photo (mandatory)
    $photoUploaded = isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK;
    if (!$photoUploaded) {
        $errors[] = 'Profile photo is required.';
    } else {
        if ($_FILES['profile_photo']['size'] > MAX_PHOTO_SIZE) {
            $errors[] = 'Profile photo must be 5MB or smaller.';
        }
        $photoType = $_FILES['profile_photo']['type'];
        if (!in_array($photoType, ALLOWED_PHOTO_TYPES, true)) {
            $errors[] = 'Profile photo must be a JPG, PNG, or WebP image.';
        }
    }

    // Check if email/phone already exists
    if (empty($errors)) {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$formData['email']]);
        if ($stmt->fetch()) $errors[] = 'Email is already registered.';
        
        // Phone number can be shared across profiles (family registrations)
    }

    // Stash uploaded photo to a temp folder until OTP verification completes
    $tempPhotoName = null;
    if (empty($errors) && $photoUploaded) {
        $tempDir = UPLOADS_PATH . 'pending_photos' . DIRECTORY_SEPARATOR;
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        if (empty($ext)) {
            $ext = ($photoType === 'image/png') ? 'png' : (($photoType === 'image/webp') ? 'webp' : 'jpg');
        }
        $tempPhotoName = 'pending_' . bin2hex(random_bytes(8)) . '.' . strtolower($ext);
        if (!move_uploaded_file($_FILES['profile_photo']['tmp_name'], $tempDir . $tempPhotoName)) {
            $errors[] = 'Failed to save profile photo. Please try again.';
            $tempPhotoName = null;
        }
    }

    // Send OTP and redirect to verification screen
    if (empty($errors)) {
        $otp = generateOTP();
        if (saveOTP($formData['email'], $otp, 'registration')) {
            $subject = 'Email Verification OTP - ' . SITE_NAME;
            $body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: #C0392B; color: #fff; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 5px 5px; }
                        .otp { font-size: 32px; font-weight: bold; color: #C0392B; text-align: center; margin: 20px 0; letter-spacing: 5px; }
                        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'><h2>Verify Your Email</h2></div>
                        <div class='content'>
                            <p>Dear {$formData['name']},</p>
                            <p>Thank you for registering at " . SITE_NAME . ". Please use the following OTP to verify your email address:</p>
                            <div class='otp'>$otp</div>
                            <p><strong>This OTP is valid for " . OTP_EXPIRY_MINUTES . " minutes.</strong></p>
                            <p>If you did not register, please ignore this email.</p>
                        </div>
                        <div class='footer'><p>&copy; " . date('Y') . " " . SITE_NAME . ". All rights reserved.</p></div>
                    </div>
                </body>
                </html>
            ";

            if (sendEmail($formData['email'], $subject, $body)) {
                // Store form data in session for verification step
                $pendingData = $formData;
                $pendingData['password'] = password_hash($formData['password'], PASSWORD_DEFAULT);
                unset($pendingData['confirm_password']);
                $pendingData['photo_temp'] = $tempPhotoName;
                $_SESSION['pending_registration'] = $pendingData;
                redirect(SITE_URL . '/verify-otp.php');
            } else {
                $errors[] = 'Failed to send verification email. Please try again later.';
            }
        } else {
            $errors[] = 'Failed to generate OTP. Please try again.';
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
                    
                    <form method="POST" action="" id="registerForm" enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <!-- Profile For -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">This profile is for <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                <?php 
                                $profileTypes = ['Myself', 'Son', 'Daughter'];
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
                                    <?php
                                    $countryCodes = [
                                        '+91' => 'India (+91)',
                                        '+1-us' => 'USA (+1)',
                                        '+1-ca' => 'Canada (+1)',
                                        '+61' => 'Australia (+61)',
                                        '+64' => 'New Zealand (+64)',
                                        '+44' => 'UK (+44)',
                                        '+355' => 'Albania (+355)',
                                        '+376' => 'Andorra (+376)',
                                        '+43' => 'Austria (+43)',
                                        '+375' => 'Belarus (+375)',
                                        '+32' => 'Belgium (+32)',
                                        '+387' => 'Bosnia and Herzegovina (+387)',
                                        '+359' => 'Bulgaria (+359)',
                                        '+385' => 'Croatia (+385)',
                                        '+357' => 'Cyprus (+357)',
                                        '+420' => 'Czech Republic (+420)',
                                        '+45' => 'Denmark (+45)',
                                        '+372' => 'Estonia (+372)',
                                        '+358' => 'Finland (+358)',
                                        '+33' => 'France (+33)',
                                        '+49' => 'Germany (+49)',
                                        '+30' => 'Greece (+30)',
                                        '+36' => 'Hungary (+36)',
                                        '+354' => 'Iceland (+354)',
                                        '+353' => 'Ireland (+353)',
                                        '+39' => 'Italy (+39)',
                                        '+383' => 'Kosovo (+383)',
                                        '+371' => 'Latvia (+371)',
                                        '+423' => 'Liechtenstein (+423)',
                                        '+370' => 'Lithuania (+370)',
                                        '+352' => 'Luxembourg (+352)',
                                        '+356' => 'Malta (+356)',
                                        '+373' => 'Moldova (+373)',
                                        '+377' => 'Monaco (+377)',
                                        '+382' => 'Montenegro (+382)',
                                        '+31' => 'Netherlands (+31)',
                                        '+389' => 'North Macedonia (+389)',
                                        '+47' => 'Norway (+47)',
                                        '+48' => 'Poland (+48)',
                                        '+351' => 'Portugal (+351)',
                                        '+40' => 'Romania (+40)',
                                        '+7' => 'Russia (+7)',
                                        '+378' => 'San Marino (+378)',
                                        '+381' => 'Serbia (+381)',
                                        '+421' => 'Slovakia (+421)',
                                        '+386' => 'Slovenia (+386)',
                                        '+34' => 'Spain (+34)',
                                        '+46' => 'Sweden (+46)',
                                        '+41' => 'Switzerland (+41)',
                                        '+90' => 'Turkey (+90)',
                                        '+380' => 'Ukraine (+380)',
                                        '+379' => 'Vatican City (+379)',
                                    ];
                                    $selectedCode = $formData['country_code'] ?? '+91';
                                    ?>
                                    <select name="country_code" class="form-select" style="max-width: 150px;">
                                        <?php foreach ($countryCodes as $val => $label): ?>
                                            <option value="<?= $val ?>" <?= $selectedCode === $val ? 'selected' : '' ?>><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?= htmlspecialchars(preg_replace('/^\+?\d+\s*/', '', $formData['phone'] ?? '')) ?>" required placeholder="Mobile number">
                                </div>
                            </div>
                            
                            <!-- Date of Birth -->
                            <div class="col-md-6">
                                <label for="dob" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="dob" name="dob" 
                                       value="<?= $formData['dob'] ?? '' ?>" required max="<?= date('Y-m-d', strtotime('-18 years')) ?>">
                                <small class="text-muted">Minimum age: Males 21 years, Females 18 years</small>
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
                                <?php
                                $currentMT = $formData['mother_tongue'] ?? '';
                                $isPresetMT = in_array($currentMT, $MOTHER_TONGUES, true);
                                $customMT = (!$isPresetMT && $currentMT !== '') ? $currentMT : '';
                                $selectValue = (!$isPresetMT && $currentMT !== '') ? 'Others' : $currentMT;
                                ?>
                                <select class="form-select" id="mother_tongue" name="mother_tongue">
                                    <option value="">Select Language</option>
                                    <?php foreach ($MOTHER_TONGUES as $lang): ?>
                                        <option value="<?= $lang ?>" <?= $selectValue === $lang ? 'selected' : '' ?>><?= $lang ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" class="form-control mt-2" id="mother_tongue_other" name="mother_tongue_other"
                                       value="<?= htmlspecialchars($customMT) ?>" placeholder="Please specify your language"
                                       style="display: <?= $selectValue === 'Others' ? 'block' : 'none' ?>;">
                            </div>
                            
                            <!-- Country -->
                            <div class="col-md-6">
                                <label for="country" class="form-label">Country</label>
                                <select class="form-select" id="country_select" data-selected-name="<?= htmlspecialchars($formData['country'] ?? 'India') ?>">
                                    <option value="">Loading countries...</option>
                                </select>
                                <input type="hidden" name="country" id="country" value="<?= htmlspecialchars($formData['country'] ?? 'India') ?>">
                            </div>
                            
                            <!-- State -->
                            <div class="col-md-6">
                                <label for="state" class="form-label">State</label>
                                <select class="form-select" id="state" name="state" data-selected="<?= htmlspecialchars($formData['state'] ?? '') ?>" disabled>
                                    <option value="">Select Country First</option>
                                </select>
                            </div>
                            
                            <!-- City -->
                            <div class="col-12">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city" 
                                       value="<?= $formData['city'] ?? '' ?>" placeholder="Enter city">
                            </div>
                            
                            <!-- Password -->
                            <div class="col-md-6">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" 
                                           required minlength="8" placeholder="Min 8 chars: 1 letter, 1 number, 1 special char">
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

                            <!-- Profile Photo (Mandatory) -->
                            <div class="mb-3">
                            <label for="profile_photo" class="form-label">Profile Photo <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="profilePhotoInput" name="profile_photo" accept="image/jpeg,image/png,image/webp" required>
                            <input type="hidden" id="croppedImageData" name="cropped_image_data">
                            <small class="text-muted">Upload a clear photo of yourself. Max size: 5MB.</small>
                            <div id="photoSizeError" class="text-danger d-none mt-1">
                                <small><i class="bi bi-exclamation-triangle me-1"></i>Selected file exceeds 5MB. Please choose a smaller image.</small>
                            </div>
                            <div id="photoPreviewWrap" class="mt-2 d-none">
                                <img id="photoPreview" src="" alt="Profile preview" style="width:120px;height:120px;object-fit:cover;border-radius:8px;border:1px solid #ddd;">
                                <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="reCropBtn"><i class="bi bi-crop me-1"></i>Re-crop</button>
                            </div>
                        </div>
                        </div>

                        <!-- Crop Modal -->
                        <div class="modal fade" id="cropModal" tabindex="-1" data-bs-backdrop="false">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><i class="bi bi-crop me-2"></i>Crop Your Photo</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div style="max-height:500px;">
                                            <img id="cropImage" style="max-width:100%;display:block;">
                                        </div>
                                        <small class="text-muted d-block mt-2"><i class="bi bi-info-circle me-1"></i>Drag to reposition, use corners to resize. Aspect ratio is locked to square for best profile display.</small>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-primary" id="cropConfirmBtn"><i class="bi bi-check-lg me-1"></i>Confirm Crop</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Terms -->
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="<?= SITE_URL ?>/terms-of-service.php" target="_blank">Terms of Service</a>, <a href="<?= SITE_URL ?>/privacy-policy.php" target="_blank">Privacy Policy</a>, and <a href="<?= SITE_URL ?>/refund-policy.php" target="_blank">Refund Policy</a>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg w-100 mt-4" id="registerBtn">
                            <i class="bi bi-person-plus me-2"></i>Register Free
                        </button>
                        
                        <div class="text-center mt-3">
                            <p>Already have an account? <a href="<?= SITE_URL ?>/login.php" class="fw-semibold">Login here</a></p>
                        </div>
                    </form>

                    <!-- Loading Overlay -->
                    <div id="loadingOverlay" class="loading-overlay d-none">
                        <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-light mt-3">Processing your registration...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// Mother tongue "Others" toggle
(function() {
    var mtSelect = document.getElementById('mother_tongue');
    var mtOther = document.getElementById('mother_tongue_other');
    if (mtSelect && mtOther) {
        mtSelect.addEventListener('change', function() {
            if (this.value === 'Others') {
                mtOther.style.display = 'block';
                mtOther.focus();
            } else {
                mtOther.style.display = 'none';
                mtOther.value = '';
            }
        });
    }
})();

document.querySelectorAll('input[name="gender"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        var dobInput = document.getElementById('dob');
        var today = new Date();
        var minAge = (this.value === 'Female') ? 18 : 21;
        var maxDate = new Date(today.getFullYear() - minAge, today.getMonth(), today.getDate());
        dobInput.max = maxDate.toISOString().split('T')[0];
    });
});

// Country/State cascading dropdown
(function() {
    var countrySelect = document.getElementById('country_select');
    var countryHidden = document.getElementById('country');
    var stateSelect = document.getElementById('state');
    var selectedCountryName = countrySelect.dataset.selectedName || 'India';
    var selectedState = stateSelect.dataset.selected || '';
    var countriesData = {};

    function populateStates(countryCode) {
        stateSelect.innerHTML = '';
        if (!countryCode || !countriesData[countryCode]) {
            stateSelect.innerHTML = '<option value="">Select Country First</option>';
            stateSelect.disabled = true;
            return;
        }
        var divisions = countriesData[countryCode].divisions || {};
        var keys = Object.keys(divisions).sort(function(a, b) {
            return divisions[a].localeCompare(divisions[b]);
        });
        var defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.textContent = 'Select State';
        stateSelect.appendChild(defaultOpt);
        keys.forEach(function(k) {
            var opt = document.createElement('option');
            opt.value = divisions[k];
            opt.textContent = divisions[k];
            if (divisions[k] === selectedState) opt.selected = true;
            stateSelect.appendChild(opt);
        });
        stateSelect.disabled = false;
    }

    fetch('<?= SITE_URL ?>/assets/data/iso-3166-2.json')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            countriesData = data;
            // Populate countries sorted by name
            var codes = Object.keys(data).sort(function(a, b) {
                return data[a].name.localeCompare(data[b].name);
            });
            countrySelect.innerHTML = '<option value="">Select Country</option>';
            var matchedCode = '';
            codes.forEach(function(code) {
                var opt = document.createElement('option');
                opt.value = code;
                opt.textContent = data[code].name;
                if (data[code].name === selectedCountryName) {
                    opt.selected = true;
                    matchedCode = code;
                }
                countrySelect.appendChild(opt);
            });
            // Auto-populate state if a country is preselected
            if (matchedCode) {
                countryHidden.value = data[matchedCode].name;
                populateStates(matchedCode);
            }
        })
        .catch(function() {
            countrySelect.innerHTML = '<option value="">Failed to load countries</option>';
        });

    countrySelect.addEventListener('change', function() {
        selectedState = '';
        var code = this.value;
        countryHidden.value = (code && countriesData[code]) ? countriesData[code].name : '';
        populateStates(code);
    });
})();
</script>

<!-- Loading Overlay CSS -->
<style>
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}
</style>

<!-- Loading Overlay Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('registerForm');
    var loadingOverlay = document.getElementById('loadingOverlay');
    var registerBtn = document.getElementById('registerBtn');

    if (form && loadingOverlay && registerBtn) {
        form.addEventListener('submit', function(e) {
            // Check if we're using the cropped blob submission (already handled by cropper JS)
            if (window.croppedBlob) {
                return; // Let the cropper handler deal with it
            }
            // Show loading overlay for normal form submission
            loadingOverlay.classList.remove('d-none');
            registerBtn.disabled = true;
        });
    }
});
</script>

<!-- Cropper.js for photo cropping -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.css">
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.js"></script>
<style>
#cropModal .modal-dialog {
    z-index: 1060 !important;
}
#cropModal .modal-content {
    z-index: 1061 !important;
}
#cropModal .modal-footer {
    position: relative;
    z-index: 1062 !important;
}
#cropModal .cropper-container {
    z-index: 1;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var input = document.getElementById('profilePhotoInput');
    var cropImage = document.getElementById('cropImage');
    var cropModalEl = document.getElementById('cropModal');
    var cropConfirmBtn = document.getElementById('cropConfirmBtn');
    var errDiv = document.getElementById('photoSizeError');
    var previewWrap = document.getElementById('photoPreviewWrap');
    var previewImg = document.getElementById('photoPreview');
    var reCropBtn = document.getElementById('reCropBtn');
    var croppedImageData = document.getElementById('croppedImageData');
    var cropper = null;
    var cropModal = cropModalEl ? new bootstrap.Modal(cropModalEl) : null;
    var croppedBlob = null;
    var originalFile = null;

    if (!input || !cropModal) return;

    // Restore cropped image from hidden field if present (after validation error)
    if (croppedImageData && croppedImageData.value) {
        previewImg.src = croppedImageData.value;
        previewWrap.classList.remove('d-none');
        input.removeAttribute('required');
        croppedBlob = null; // Will be recreated from base64 when needed
    }

    input.addEventListener('change', function() {
        if (!this.files.length) return;
        var file = this.files[0];
        originalFile = file;

        // 5MB client-side validation
        if (file.size > 5 * 1024 * 1024) {
            errDiv.classList.remove('d-none');
            this.value = '';
            croppedBlob = null;
            originalFile = null;
            previewWrap.classList.add('d-none');
            return;
        }
        errDiv.classList.add('d-none');

        var reader = new FileReader();
        reader.onload = function(e) {
            cropImage.src = e.target.result;
            cropImage.style.display = 'block';
            cropModal.show();
        };
        reader.onerror = function() {
            alert('Failed to read the image file. Please try another file.');
            input.value = '';
        };
        reader.readAsDataURL(file);
    });

    cropModalEl.addEventListener('shown.bs.modal', function() {
        if (cropper) cropper.destroy();

        if (cropImage.complete) {
            initCropper();
        } else {
            cropImage.onload = initCropper;
            cropImage.onerror = function() {
                console.error('Image failed to load');
                alert('Failed to load the image. Please try another file.');
                cropModal.hide();
            };
        }

        function initCropper() {
            try {
                cropper = new Cropper(cropImage, {
                    aspectRatio: 1,
                    viewMode: 1,
                    autoCropArea: 1,
                    movable: true,
                    zoomable: true,
                    scalable: false,
                    rotatable: false,
                    background: false
                });
            } catch (e) {
                console.error('Cropper initialization error:', e);
                alert('Failed to initialize image cropper. Please try again.');
                cropModal.hide();
            }
        }
    });

    cropModalEl.addEventListener('hidden.bs.modal', function() {
        if (cropper) { cropper.destroy(); cropper = null; }
        if (!croppedBlob) {
            input.value = '';
        }
    });

    cropConfirmBtn.addEventListener('click', function() {
        if (!cropper) {
            alert('Cropper not initialized. Please try selecting the image again.');
            return;
        }
        cropConfirmBtn.disabled = true;
        cropConfirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Cropping...';

        try {
            cropper.getCroppedCanvas({
                width: 800,
                height: 800,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            }).toBlob(function(blob) {
                if (!blob) {
                    alert('Failed to crop image. Please try again.');
                    cropConfirmBtn.disabled = false;
                    cropConfirmBtn.innerHTML = '<i class="bi bi-crop me-1"></i>Crop';
                    return;
                }

                croppedBlob = blob;
                var url = URL.createObjectURL(blob);
                previewImg.src = url;
                previewWrap.classList.remove('d-none');

                // Save cropped image as base64 to hidden field for persistence across page reloads
                var reader = new FileReader();
                reader.onload = function(e) {
                    if (croppedImageData) {
                        croppedImageData.value = e.target.result;
                    }
                    cropModal.hide();
                };
                reader.readAsDataURL(blob);

                cropConfirmBtn.disabled = false;
                cropConfirmBtn.innerHTML = '<i class="bi bi-crop me-1"></i>Crop';
            }, 'image/jpeg', 0.95);
        } catch (e) {
            console.error('Cropping error:', e);
            alert('Failed to crop image. Please try again.');
            cropConfirmBtn.disabled = false;
            cropConfirmBtn.innerHTML = '<i class="bi bi-crop me-1"></i>Crop';
        }
    });

    reCropBtn.addEventListener('click', function() {
        // If there's a file in the input, use it
        if (input.files.length) {
            var file = input.files[0];
            var reader = new FileReader();
            reader.onload = function(e) {
                cropImage.src = e.target.result;
                cropImage.style.display = 'block';
                cropModal.show();
            };
            reader.readAsDataURL(file);
        }
        // Otherwise, use the base64 data from the hidden field (after page reload)
        else if (croppedImageData && croppedImageData.value) {
            cropImage.src = croppedImageData.value;
            cropImage.style.display = 'block';
            cropModal.show();
        }
    });

    // Override form submission to include cropped blob
    var form = document.getElementById('registerForm');
    var loadingOverlay = document.getElementById('loadingOverlay');
    var registerBtn = document.getElementById('registerBtn');
    form.addEventListener('submit', function(e) {
        // Check if we have a cropped blob or base64 data
        var hasCroppedImage = croppedBlob || (croppedImageData && croppedImageData.value);
        if (!hasCroppedImage) {
            // No crop performed, submit normally (handled by the loading overlay script)
            return;
        }
        e.preventDefault();

        // Show loading overlay
        if (loadingOverlay) loadingOverlay.classList.remove('d-none');
        if (registerBtn) registerBtn.disabled = true;

        var submitForm = function(blob) {
            var formData = new FormData(form);
            formData.set('profile_photo', blob, 'cropped.jpg');

            fetch(form.action || window.location.href, {
                method: 'POST',
                body: formData
            }).then(function(res) {
                if (res.redirected) {
                    window.location.href = res.url;
                } else {
                    return res.text();
                }
            }).then(function(html) {
                if (html) {
                    document.documentElement.innerHTML = html;
                }
            }).catch(function(err) {
                console.error('Submit error:', err);
                alert('Submission failed. Please try again.');
                if (loadingOverlay) loadingOverlay.classList.add('d-none');
                if (registerBtn) registerBtn.disabled = false;
            });
        };

        // If we have a blob, use it directly
        if (croppedBlob) {
            submitForm(croppedBlob);
        }
        // Otherwise, convert base64 to blob
        else if (croppedImageData && croppedImageData.value) {
            fetch(croppedImageData.value)
                .then(function(res) { return res.blob(); })
                .then(function(blob) {
                    croppedBlob = blob;
                    submitForm(blob);
                })
                .catch(function(err) {
                    console.error('Base64 to blob conversion error:', err);
                    alert('Failed to process cropped image. Please try selecting the image again.');
                    if (loadingOverlay) loadingOverlay.classList.add('d-none');
                    if (registerBtn) registerBtn.disabled = false;
                });
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
