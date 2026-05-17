<?php
/**
 * Registration payment screen.
 *
 * Shown immediately after `verify-otp.php` while the user is NOT yet logged
 * in. Auto-loads the gender-priced plan, lets the user apply a coupon, and
 * either launches Razorpay checkout or bypasses it entirely on a 100% coupon.
 */
$pageTitle = 'Complete Registration Payment';
require_once __DIR__ . '/includes/functions.php';

// Gate: must come from verify-otp.php within the session window.
$pendingUserId = (int)($_SESSION['registration_payment_user_id'] ?? 0);
$expiresAt     = (int)($_SESSION['registration_payment_expires'] ?? 0);
if (!$pendingUserId || time() > $expiresAt) {
    setFlash('error', 'Your registration session has expired. Please register again.');
    redirect(SITE_URL . '/register.php');
}

$pdo = getDBConnection();

// Re-fetch the user (status, gender, payment status)
$stmt = $pdo->prepare("SELECT id, name, email, gender, registration_payment_status FROM users WHERE id = ?");
$stmt->execute([$pendingUserId]);
$user = $stmt->fetch();

if (!$user) {
    unset($_SESSION['registration_payment_user_id'], $_SESSION['registration_payment_expires']);
    setFlash('error', 'Your account could not be located. Please register again.');
    redirect(SITE_URL . '/register.php');
}

// Already paid (e.g. user refreshed) → straight to success.
if (in_array($user['registration_payment_status'], ['completed', 'bypassed'], true)) {
    redirect(SITE_URL . '/registration-success.php');
}

// Resolve the gender-based plan
$plan = getRegistrationPlanForGender($user['gender']);
if (!$plan) {
    setFlash('error', 'No registration plan is currently configured. Please contact support.');
    redirect(SITE_URL . '/login.php');
}

$razorpayConfigured = isRazorpayConfigured();

require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-9">
                <div class="auth-card">
                    <div class="auth-header text-center">
                        <h2><i class="bi bi-credit-card-2-front"></i> Complete Registration</h2>
                        <p>Hi <strong><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></strong>, please complete the one-time registration payment to submit your profile for admin review.</p>
                    </div>

                    <?php if (!$razorpayConfigured): ?>
                        <div class="alert alert-warning small">
                            <i class="bi bi-info-circle me-1"></i>
                            Razorpay is not configured on this environment yet.
                            You can still complete registration by applying a <strong>100% discount coupon</strong>.
                        </div>
                    <?php endif; ?>

                    <div class="card mb-3">
                        <div class="card-body">
                            <h6 class="card-title mb-3"><i class="bi bi-bookmark-star me-1"></i><?= htmlspecialchars($plan['name'], ENT_QUOTES, 'UTF-8') ?></h6>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Subscription price</span>
                                <span id="amount-original">&#8377;<?= number_format((float)$plan['price'], 2) ?></span>
                            </div>
                            <div class="d-flex justify-content-between text-success mb-1 d-none" id="discount-row">
                                <span>Coupon discount (<span id="coupon-percent">0</span>%)</span>
                                <span>&minus;&#8377;<span id="amount-discount">0.00</span></span>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between fs-5">
                                <strong>Payable</strong>
                                <strong>&#8377;<span id="amount-final"><?= number_format((float)$plan['price'], 2) ?></span></strong>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Have a coupon?</label>
                        <div class="input-group">
                            <input type="text" id="coupon-code" class="form-control text-uppercase" placeholder="Enter coupon code" maxlength="40" autocomplete="off">
                            <button class="btn btn-outline-primary" type="button" id="btn-apply-coupon">Apply</button>
                            <button class="btn btn-outline-secondary d-none" type="button" id="btn-remove-coupon">Remove</button>
                        </div>
                        <div class="form-text" id="coupon-msg"></div>
                    </div>

                    <button id="btn-pay" type="button" class="btn btn-primary btn-lg w-100" <?= !$razorpayConfigured ? 'disabled' : '' ?>>
                        <i class="bi bi-shield-lock me-1"></i>
                        <span id="btn-pay-label">Pay Securely with Razorpay</span>
                    </button>

                    <p class="text-muted text-center small mt-3 mb-0">
                        Your account will be submitted for admin review once payment is completed.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($razorpayConfigured): ?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<?php endif; ?>
<script>
// jQuery is loaded by includes/footer.php AFTER this <script> block, so we
// must wait until DOMContentLoaded — by then the parser has executed every
// <script> tag in the body, including jQuery and Razorpay checkout.js.
document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.jQuery === 'undefined') {
        console.error('registration-payment: jQuery not loaded');
        var el = document.getElementById('coupon-msg');
        if (el) { el.className = 'form-text text-danger'; el.textContent = 'Page failed to load required scripts. Refresh the page.'; }
        return;
    }
    var $ = window.jQuery;
    var planId          = <?= (int)$plan['id'] ?>;
    var originalAmount  = <?= number_format((float)$plan['price'], 2, '.', '') ?>;
    var appliedCoupon   = null;       // server-confirmed coupon code or null
    var razorpayEnabled = <?= $razorpayConfigured ? 'true' : 'false' ?>;
    var razorpayKey     = <?= json_encode(defined('RAZORPAY_KEY_ID') ? RAZORPAY_KEY_ID : '') ?>;
    var siteName        = <?= json_encode(SITE_NAME) ?>;
    var userEmail       = <?= json_encode($user['email']) ?>;
    var userName        = <?= json_encode($user['name']) ?>;
    var csrfToken       = <?= json_encode(generateCSRFToken()) ?>;

    // Send CSRF on every same-origin AJAX so requireCSRF() on the API passes.
    $.ajaxSetup({
        headers: { 'X-CSRF-Token': csrfToken }
    });

    function fmt(n) { return Number(n).toFixed(2); }

    function setSummary(discountPercent, discountAmount, finalAmount) {
        if (discountPercent > 0) {
            $('#discount-row').removeClass('d-none');
            $('#coupon-percent').text(discountPercent);
            $('#amount-discount').text(fmt(discountAmount));
        } else {
            $('#discount-row').addClass('d-none');
        }
        $('#amount-final').text(fmt(finalAmount));

        if (Number(finalAmount) === 0) {
            $('#btn-pay-label').text('Complete Registration (Free)');
            $('#btn-pay').prop('disabled', false).removeClass('btn-primary').addClass('btn-success');
        } else {
            $('#btn-pay-label').text('Pay Securely with Razorpay');
            $('#btn-pay').prop('disabled', !razorpayEnabled)
                        .removeClass('btn-success').addClass('btn-primary');
        }
    }

    // Reset summary on load (covers refresh)
    setSummary(0, 0, originalAmount);

    $('#btn-apply-coupon').on('click', function () {
        var code = String($('#coupon-code').val() || '').trim().toUpperCase();
        if (!code) { $('#coupon-msg').removeClass('text-success').addClass('text-danger').text('Please enter a code.'); return; }
        $(this).prop('disabled', true).text('Checking...');

        $.ajax({
            url: 'api/apply-coupon.php',
            method: 'POST',
            data: { code: code },
            dataType: 'json'
        }).done(function (r) {
            if (r.success) {
                appliedCoupon = code;
                setSummary(r.discount_percent, r.discount_amount, r.final_amount);
                $('#coupon-msg').removeClass('text-danger').addClass('text-success')
                    .text('Coupon applied: ' + r.discount_percent + '% off');
                $('#coupon-code').prop('readonly', true);
                $('#btn-apply-coupon').addClass('d-none');
                $('#btn-remove-coupon').removeClass('d-none');
            } else {
                appliedCoupon = null;
                setSummary(0, 0, originalAmount);
                $('#coupon-msg').removeClass('text-success').addClass('text-danger').text(r.message || 'Could not apply coupon.');
            }
        }).fail(function () {
            $('#coupon-msg').removeClass('text-success').addClass('text-danger').text('Request failed. Try again.');
        }).always(function () {
            $('#btn-apply-coupon').prop('disabled', false).text('Apply');
        });
    });

    $('#btn-remove-coupon').on('click', function () {
        appliedCoupon = null;
        $('#coupon-code').val('').prop('readonly', false);
        $('#coupon-msg').text('');
        $('#btn-apply-coupon').removeClass('d-none');
        $(this).addClass('d-none');
        setSummary(0, 0, originalAmount);
    });

    $('#btn-pay').on('click', function () {
        var btn = $(this);
        btn.prop('disabled', true);
        var origLabel = $('#btn-pay-label').text();
        $('#btn-pay-label').text('Please wait...');

        $.ajax({
            url: 'api/registration-payment.php',
            method: 'POST',
            data: {
                action:    'initiate',
                plan_id:   planId,
                coupon:    appliedCoupon || ''
            },
            dataType: 'json'
        }).done(function (r) {
            if (!r.success) {
                alert(r.message || 'Could not start payment.');
                btn.prop('disabled', false);
                $('#btn-pay-label').text(origLabel);
                return;
            }

            // 100% coupon → server already marked the payment complete.
            if (r.bypass) {
                window.location.href = 'registration-success.php';
                return;
            }

            if (!razorpayEnabled || !window.Razorpay) {
                alert('Razorpay is not available on this environment. Please apply a 100% coupon or contact support.');
                btn.prop('disabled', false);
                $('#btn-pay-label').text(origLabel);
                return;
            }

            var rzp = new Razorpay({
                key:         razorpayKey,
                amount:      r.amount_paise,
                currency:    r.currency || 'INR',
                name:        siteName,
                description: r.plan_name,
                order_id:    r.razorpay_order_id,
                prefill:     { name: userName, email: userEmail },
                handler: function (resp) {
                    $.ajax({
                        url: 'api/registration-payment.php',
                        method: 'POST',
                        data: {
                            action:               'verify',
                            registration_payment_id: r.registration_payment_id,
                            razorpay_order_id:    resp.razorpay_order_id,
                            razorpay_payment_id:  resp.razorpay_payment_id,
                            razorpay_signature:   resp.razorpay_signature
                        },
                        dataType: 'json'
                    }).done(function (v) {
                        if (v.success) {
                            window.location.href = 'registration-success.php';
                        } else {
                            alert(v.message || 'Payment verification failed. Please contact support.');
                            btn.prop('disabled', false);
                            $('#btn-pay-label').text(origLabel);
                        }
                    }).fail(function () {
                        alert('Verification request failed. Please contact support with your payment id: ' + resp.razorpay_payment_id);
                    });
                },
                modal: {
                    ondismiss: function () {
                        btn.prop('disabled', false);
                        $('#btn-pay-label').text(origLabel);
                    }
                }
            });
            rzp.open();
        }).fail(function () {
            alert('Could not start payment. Please try again.');
            btn.prop('disabled', false);
            $('#btn-pay-label').text(origLabel);
        });
    });
}); // end DOMContentLoaded
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
