<?php
$pageTitle = 'Privacy Policy';
require_once __DIR__ . '/includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body p-5">
                        <h1 class="mb-4">Privacy Policy</h1>
                        <p class="text-muted mb-4">Last updated: <?= date('F j, Y') ?></p>
                        
                        <h4 class="mt-4 mb-3">1. Information We Collect</h4>
                        <p>We collect information you provide directly to us when you register for an account, including your name, email address, phone number, date of birth, gender, religion, caste, and other profile information. We also collect information about your use of our services, such as your profile views, search history, and interactions with other users.</p>
                        
                        <h4 class="mt-4 mb-3">2. How We Use Your Information</h4>
                        <p>We use the information we collect to provide, maintain, and improve our services, to process your registrations and subscriptions, to communicate with you about our services, and to protect against fraudulent or illegal activity. We may also use your information to personalize your experience on our platform.</p>
                        
                        <h4 class="mt-4 mb-3">3. Information Sharing</h4>
                        <p>We do not sell your personal information to third parties. We may share your information with service providers who perform services on our behalf, such as hosting, data processing, and analytics. We may also disclose your information if required by law or to protect our rights, property, or safety.</p>
                        
                        <h4 class="mt-4 mb-3">4. Data Security</h4>
                        <p>We implement reasonable security measures to protect your personal information from unauthorized access, use, or disclosure. However, no method of transmission over the Internet is 100% secure, and we cannot guarantee absolute security.</p>
                        
                        <h4 class="mt-4 mb-3">5. Your Rights</h4>
                        <p>You have the right to access, update, or delete your personal information. You can do this by logging into your account and updating your profile settings. You may also request deletion of your account by contacting our support team.</p>
                        
                        <h4 class="mt-4 mb-3">6. Cookies</h4>
                        <p>We use cookies and similar technologies to improve your experience, analyze usage, and assist in our marketing efforts. You can control cookies through your browser settings.</p>
                        
                        <h4 class="mt-4 mb-3">7. Children's Privacy</h4>
                        <p>Our services are not intended for individuals under 18 years of age. We do not knowingly collect personal information from children under 18.</p>
                        
                        <h4 class="mt-4 mb-3">8. Changes to This Policy</h4>
                        <p>We may update this privacy policy from time to time. We will notify you of any changes by posting the new policy on this page and updating the "Last updated" date.</p>
                        
                        <h4 class="mt-4 mb-3">9. Contact Us</h4>
                        <p>If you have any questions about this privacy policy, please contact us at <?= SITE_EMAIL ?>.</p>
                        
                        <div class="mt-5">
                            <a href="<?= SITE_URL ?>" class="btn btn-primary">Back to Home</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
