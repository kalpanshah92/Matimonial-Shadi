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
    
    // Check if email/phone already exists
    if (empty($errors)) {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$formData['email']]);
        if ($stmt->fetch()) $errors[] = 'Email is already registered.';
        
        // Phone number can be shared across profiles (family registrations)
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
                    
                    <form method="POST" action="" id="registerForm" novalidate>
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
                        </div>
                        
                        <!-- Terms -->
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="<?= SITE_URL ?>/terms-of-service.php" target="_blank">Terms of Service</a>, <a href="<?= SITE_URL ?>/privacy-policy.php" target="_blank">Privacy Policy</a>, and <a href="<?= SITE_URL ?>/refund-policy.php" target="_blank">Refund Policy</a>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
