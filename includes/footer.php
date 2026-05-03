</main>

<!-- Footer -->
<footer class="site-footer">
    <div class="footer-top">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="footer-brand">
                        <h3><i class="bi bi-hearts"></i> <?= SITE_NAME ?></h3>
                        <p>Gujarat's trusted matrimonial platform connecting hearts across communities and cultures. Find your perfect life partner with us.</p>
                        <div class="social-links mt-3">
                            <a href="#"><i class="bi bi-facebook"></i></a>
                            <a href="#"><i class="bi bi-instagram"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <h5>Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="<?= SITE_URL ?>">Home</a></li>
                        <li><a href="<?= SITE_URL ?>/search.php">Search</a></li>
                        <li><a href="<?= SITE_URL ?>/subscription.php">Premium Plans</a></li>
                        <li><a href="<?= SITE_URL ?>/success-stories.php">Success Stories</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h5>Browse by Community</h5>
                    <ul class="footer-links">
                        <li><a href="<?= SITE_URL ?>/search.php?religion=Hindu">Hindu Matrimony</a></li>
                        <li><a href="<?= SITE_URL ?>/search.php?religion=Jain">Jain Matrimony</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h5>Contact Us</h5>
                    <ul class="footer-contact">
                        <li><i class="bi bi-geo-alt me-2"></i>Mumbai, Maharashtra, India</li>
                        <li><i class="bi bi-telephone me-2"></i><?= SITE_PHONE ?></li>
                        <li><i class="bi bi-envelope me-2"></i><?= SITE_EMAIL ?></li>
                        <li><i class="bi bi-clock me-2"></i>Mon - Sat: 9:00 AM - 8:00 PM</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="<?= SITE_URL ?>/privacy-policy.php">Privacy Policy</a>
                    <a href="<?= SITE_URL ?>/terms-of-service.php" class="ms-3">Terms of Service</a>
                    <a href="<?= SITE_URL ?>/refund-policy.php" class="ms-3">Refund Policy</a>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Back to Top -->
<a href="#" class="back-to-top" id="backToTop"><i class="bi bi-arrow-up"></i></a>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Custom JS -->
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>

<?php if (isset($extraJS)): ?>
    <?php foreach ($extraJS as $js): ?>
        <script src="<?= $js ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
