# Razorpay Integration Guide

This document explains how to wire Razorpay into the registration-payment
flow once you have an account. Until then, the entire flow can be exercised
end-to-end by issuing a **100% discount coupon** from the admin panel —
the system bypasses Razorpay automatically in that case.

---

## 1. Sign up & get keys

1. Create an account at https://razorpay.com.
2. Open **Dashboard → Settings → API Keys → Generate Key**.
3. Note both values — they're shown only once:
   - `Key Id`    → looks like `rzp_test_XXXXXXXXXXXX` (test mode)
                  or `rzp_live_XXXXXXXXXXXX` (live mode).
   - `Key Secret`→ random 24-char alphanumeric string.

> Start in **Test Mode** — payments use a dummy card (`4111 1111 1111 1111`,
> any future expiry, any CVV, any OTP). Switch to **Live Mode** only after
> end-to-end QA.

---

## 2. Configure the application

The app reads both values from environment variables (preferred) and falls
back to constants in `config/app.php`. **Never commit live keys to git.**

### Linux / Apache (production VM)

```bash
# /etc/apache2/envvars  (or your systemd drop-in)
export RAZORPAY_KEY_ID="rzp_live_XXXXXXXXXXXX"
export RAZORPAY_KEY_SECRET="your-secret-here"
sudo systemctl restart apache2
```

### Docker

```yaml
# docker-compose.yml
services:
  web:
    environment:
      RAZORPAY_KEY_ID:     "rzp_live_XXXXXXXXXXXX"
      RAZORPAY_KEY_SECRET: "your-secret-here"
```

### Windows (XAMPP/dev)

Edit `config/app.php` directly **for local dev only**:

```php
define('RAZORPAY_KEY_ID',     getenv('RAZORPAY_KEY_ID')     ?: 'rzp_test_XXXXXXXXXXXX');
define('RAZORPAY_KEY_SECRET', getenv('RAZORPAY_KEY_SECRET') ?: 'your-test-secret');
```

`isRazorpayConfigured()` in `includes/functions.php` returns `true` only
when both values are present and not the placeholder strings, so the UI
will automatically light up once the variables are exported.

---

## 3. How the flow uses Razorpay

```
register.php → verify-otp.php → registration-payment.php
                                   │
                                   ├── POST api/apply-coupon.php       (preview only)
                                   │
                                   └── POST api/registration-payment.php?action=initiate
                                        │
                                        ├─ 100% coupon ⇒ bypass, status='pending', return ok
                                        │
                                        └─ Razorpay path
                                            │
                                            ├─ curl POST https://api.razorpay.com/v1/orders
                                            │   (auth = Key Id : Key Secret)
                                            │
                                            └─ JS opens Razorpay Checkout
                                                │
                                                └─ on success: POST api/registration-payment.php?action=verify
                                                    └─ HMAC-SHA256(order_id|payment_id, secret) verified server-side
                                                        └─ users.registration_payment_status = 'completed'
                                                        → redirect to registration-success.php
```

Key points:

- **All amount math is server-side.** The client never tells the server how
  much to charge. `api/registration-payment.php` reads price from `plans`
  and discount from `coupons`.
- **Signature verification is mandatory.** `hash_hmac('sha256', orderId|paymentId, secret)`
  compared with `hash_equals()` — same scheme as `api/payment.php` (F-01).
- **Idempotency** is enforced by `UNIQUE KEY uniq_razorpay_payment` on
  `registration_payments.razorpay_payment_id`.

---

## 4. Webhook (recommended, not required)

For added resilience (e.g. user closes tab after paying), configure a
Razorpay **webhook** pointing at:

```
POST https://yourdomain.com/api/razorpay-webhook.php
```

Events to subscribe to: `payment.captured`, `payment.failed`.

A webhook stub is **not** included by default — it requires its own
verification (`X-Razorpay-Signature` header HMAC'd with a separate webhook
secret). Open an issue / ask Cascade to scaffold it when you're ready.

---

## 5. Going live

1. Switch dashboard from **Test Mode** → **Live Mode** and regenerate keys.
2. Export the new env vars on production.
3. KYC must be completed in your Razorpay dashboard before live payments
   are accepted.
4. Set domain in **Razorpay Dashboard → Account → Brand Configuration** so
   the checkout shows your logo/colors.

---

## 6. Local testing without Razorpay

You can validate the full registration flow today, before signing up:

1. Run the migration: `database/migrations/registration_payment_and_coupons.sql`.
2. Log in to `/mineadmin/coupons.php` as super_admin → **New Coupon**:
   - Code: `FREE100`
   - Discount %: `100`
   - Max Redemptions: leave blank
3. Register a new user → finish OTP → enter `FREE100` on the payment page.
   The system marks the payment as `bypassed` and routes to the success
   page. No Razorpay round trip.
