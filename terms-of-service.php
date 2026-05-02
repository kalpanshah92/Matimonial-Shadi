<?php
$pageTitle = 'Terms of Service';
require_once __DIR__ . '/includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body p-5">
                        <h1 class="mb-4">Terms of Service</h1>
                        <p class="text-muted mb-4">Last updated: <?= date('F j, Y') ?></p>
                        
                        <h4 class="mt-4 mb-3">1. Acceptance of Terms</h4>
                        <p>By accessing or using <?= SITE_NAME ?>, you agree to be bound by these Terms of Service. If you do not agree to these terms, please do not use our service.</p>
                        
                        <h4 class="mt-4 mb-3">2. Eligibility</h4>
                        <p>You must be at least 18 years old to use our service. By using our service, you represent and warrant that you are at least 18 years old and have the legal capacity to enter into these terms.</p>
                        
                        <h4 class="mt-4 mb-3">3. Account Registration</h4>
                        <p>To use our service, you must create an account. You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account. You agree to provide accurate and complete information during registration.</p>
                        
                        <h4 class="mt-4 mb-3">4. User Conduct</h4>
                        <p>You agree not to use our service for any illegal purpose, to harass or harm other users, to impersonate any person or entity, or to upload false or misleading information. You also agree not to use automated systems to access our service without our express permission.</p>
                        
                        <h4 class="mt-4 mb-3">5. Profile Information</h4>
                        <p>You are responsible for the accuracy and completeness of your profile information. We reserve the right to remove or modify any profile information that we believe to be false, misleading, or inappropriate.</p>
                        
                        <h4 class="mt-4 mb-3">6. Communication with Other Users</h4>
                        <p>Our service allows you to communicate with other users. You agree to treat all users with respect and to refrain from sending unsolicited commercial messages or spam.</p>
                        
                        <h4 class="mt-4 mb-3">7. Premium Services</h4>
                        <p>We offer premium subscription services. By subscribing to these services, you agree to pay the applicable fees and to be bound by the specific terms of the subscription. All fees are non-refundable unless otherwise stated.</p>
                        
                        <h4 class="mt-4 mb-3">8. Intellectual Property</h4>
                        <p>All content on our service, including text, graphics, logos, and software, is the property of <?= SITE_NAME ?> and is protected by intellectual property laws. You may not use our content without our express written permission.</p>
                        
                        <h4 class="mt-4 mb-3">9. Termination</h4>
                        <p>We reserve the right to terminate or suspend your account at any time, with or without cause, with or without notice. Upon termination, your right to use the service will immediately cease.</p>
                        
                        <h4 class="mt-4 mb-3">10. Disclaimers</h4>
                        <p>Our service is provided on an "as is" and "as available" basis. We make no warranties, express or implied, regarding the operation of our service or the information, content, materials, or products included on this site.</p>
                        
                        <h4 class="mt-4 mb-3">11. Limitation of Liability</h4>
                        <p>In no event shall <?= SITE_NAME ?> be liable for any indirect, incidental, special, consequential, or punitive damages arising out of or related to your use of our service.</p>
                        
                        <h4 class="mt-4 mb-3">12. Indemnification</h4>
                        <p>You agree to indemnify and hold harmless <?= SITE_NAME ?> from any claims arising out of your use of our service or your violation of these terms.</p>
                        
                        <h4 class="mt-4 mb-3">13. Governing Law</h4>
                        <p>These terms shall be governed by and construed in accordance with the laws of India, without regard to its conflict of law provisions.</p>
                        
                        <h4 class="mt-4 mb-3">14. Changes to Terms</h4>
                        <p>We reserve the right to modify these terms at any time. Your continued use of our service after such modifications constitutes your acceptance of the new terms.</p>
                        
                        <h4 class="mt-4 mb-3">15. Contact Us</h4>
                        <p>If you have any questions about these terms, please contact us at <?= SITE_EMAIL ?>.</p>
                        
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
