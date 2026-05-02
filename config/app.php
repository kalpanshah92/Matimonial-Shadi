<?php
/**
 * Application Configuration
 */

// Site Settings
define('SITE_NAME', 'Matrimonial Shadi');
define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost/matrimonial-shadi');
define('SITE_EMAIL', 'support@matrimonialshadi.com');
define('SITE_PHONE', '+91-9876543210');

// Path Settings
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('UPLOADS_PATH', ROOT_PATH . 'uploads' . DIRECTORY_SEPARATOR);
define('UPLOADS_URL', SITE_URL . '/uploads');

// Session Settings
define('SESSION_LIFETIME', 3600); // 1 hour

// OTP Settings
define('OTP_EXPIRY_MINUTES', 10);
define('OTP_LENGTH', 6);

// Profile Settings
define('MAX_PHOTOS', 5);
define('MAX_PHOTO_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_PHOTO_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// Pagination
define('RESULTS_PER_PAGE', 12);
define('ADMIN_RESULTS_PER_PAGE', 20);

// Payment Gateway (Razorpay)
define('RAZORPAY_KEY_ID', 'your_razorpay_key_id');
define('RAZORPAY_KEY_SECRET', 'your_razorpay_key_secret');

// Email/SMTP Settings
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: 'your_email@gmail.com');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: 'your_app_password');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'noreply@matrimonialshadi.com');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Matrimonial Shadi');
define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'tls');

// Indian Communities Data
$RELIGIONS = [
    'Hindu', 'Jain'
];

$MOTHER_TONGUES = [
    'Gujarati', 'Hindi', 'Marvadi', 'English'
];

$INDIAN_STATES = [
    'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
    'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka',
    'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram',
    'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu',
    'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
    'Andaman and Nicobar Islands', 'Chandigarh', 'Dadra and Nagar Haveli and Daman and Diu',
    'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Lakshadweep', 'Puducherry'
];

$MARITAL_STATUS = ['Never Married', 'Divorced', 'Widowed', 'Awaiting Divorce', 'Annulled'];

$EDUCATION_LEVELS = [
    'High School', 'Diploma', 'Bachelor\'s Degree', 'Master\'s Degree', 'Doctorate/PhD',
    'B.Tech/B.E.', 'M.Tech/M.E.', 'MBBS', 'MD/MS', 'BDS', 'MDS',
    'B.Com', 'M.Com', 'BBA/BBM', 'MBA/PGDM', 'B.Sc', 'M.Sc',
    'BA', 'MA', 'LLB', 'LLM', 'CA', 'CS', 'ICWA', 'BCA', 'MCA', 'Other'
];

$OCCUPATIONS = [
    'Software Professional', 'Doctor', 'Engineer', 'Teacher/Professor', 'Business Owner',
    'Government Employee', 'Banker', 'Chartered Accountant', 'Lawyer', 'Architect',
    'Scientist', 'Armed Forces', 'Civil Services (IAS/IPS/IFS)', 'Pilot',
    'Consultant', 'Manager', 'Marketing Professional', 'HR Professional',
    'Designer', 'Journalist', 'Farmer', 'Self Employed', 'Not Working', 'Other'
];

$DIET_OPTIONS = ['Vegetarian', 'Non-Vegetarian', 'Eggetarian', 'Vegan', 'Jain'];

$COMPLEXION_OPTIONS = ['Very Fair', 'Fair', 'Wheatish', 'Wheatish Brown', 'Dark'];

$BODY_TYPES = ['Slim', 'Average', 'Athletic', 'Heavy'];

$FAMILY_TYPES = ['Joint Family', 'Nuclear Family'];

$FAMILY_STATUS = ['Middle Class', 'Upper Middle Class', 'Rich', 'Affluent'];

$FAMILY_VALUES = ['Orthodox', 'Traditional', 'Moderate', 'Liberal'];

$INCOME_RANGES = [
    'Below 2 Lakh', '2-4 Lakh', '4-6 Lakh', '6-8 Lakh', '8-10 Lakh',
    '10-15 Lakh', '15-20 Lakh', '20-30 Lakh', '30-50 Lakh', '50 Lakh - 1 Crore',
    'Above 1 Crore'
];

$HEIGHT_OPTIONS = [];
for ($feet = 4; $feet <= 7; $feet++) {
    for ($inches = 0; $inches <= 11; $inches++) {
        if ($feet == 7 && $inches > 0) break;
        $cm = round(($feet * 30.48) + ($inches * 2.54));
        $HEIGHT_OPTIONS["$cm"] = "$feet' $inches\" ($cm cm)";
    }
}
