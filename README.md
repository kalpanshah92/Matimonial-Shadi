# Matrimonial Shadi

A feature-rich Indian matrimonial matchmaking platform built with PHP, MySQL, HTML, CSS, and JavaScript. Designed for the Indian audience with cultural and regional preferences, community-based search, and a modern responsive UI.

## Features

- **User Registration & Login** - Email, phone with OTP verification, CSRF protection
- **Detailed Profile Creation** - Personal, professional, family details, lifestyle & partner preferences
- **Advanced Search & Filters** - Religion, caste, age, education, income, location, mother tongue
- **Dashboard** - Personalized matches, visitor tracking, connection requests, profile completion
- **Connection Requests** - Send/accept/decline interests with notifications
- **Real-time Chat** - Secure messaging between mutually connected users
- **Privacy Controls** - Granular visibility settings for phone, email, photos, income
- **Subscription Plans** - Free, Silver, Gold, Platinum with Razorpay payment gateway
- **Profile Verification** - Verified badge system for authenticity
- **Success Stories** - User-submitted success stories and testimonials
- **Admin Panel** - User management, analytics, reports, subscription management
- **Community Support** - Hindu & Jain communities + regional languages
- **Mobile Responsive** - Optimized for all devices

## Tech Stack

- **Backend:** PHP 8.0+ (vanilla, PDO)
- **Database:** MySQL 5.7+ / MariaDB 10.3+
- **Frontend:** HTML5, CSS3, JavaScript (jQuery)
- **UI Framework:** Bootstrap 5.3
- **Icons:** Bootstrap Icons
- **Fonts:** Google Fonts (Poppins, Playfair Display)
- **Payment:** Razorpay Integration
- **Server:** Apache with mod_rewrite

## Project Structure

```
├── admin/              # Admin panel
│   ├── api/            # Admin API endpoints
│   ├── includes/       # Admin sidebar & components
│   ├── index.php       # Admin dashboard
│   └── login.php       # Admin login
├── api/                # AJAX API endpoints
│   ├── chat.php        # Chat messages API
│   ├── connection.php  # Connection requests API
│   ├── notifications.php
│   ├── payment.php     # Razorpay payment handler
│   ├── report.php      # Profile reporting
│   └── shortlist.php   # Shortlist toggle API
├── assets/
│   ├── css/style.css   # Main stylesheet
│   ├── js/main.js      # Main JavaScript
│   └── images/         # Static images
├── config/
│   ├── app.php         # App configuration & constants
│   └── database.php    # Database connection (PDO)
├── database/
│   └── schema.sql      # Complete database schema
├── includes/
│   ├── auth.php        # Auth middleware
│   ├── footer.php      # Site footer
│   ├── functions.php   # Core helper functions
│   └── header.php      # Site header & navigation
├── uploads/photos/     # User uploaded photos
├── .htaccess           # Apache config & security
├── index.php           # Home page
├── register.php        # User registration
├── login.php           # User login
├── logout.php          # Logout handler
├── forgot-password.php # Password reset
├── dashboard.php       # User dashboard
├── search.php          # Profile search
├── profile.php         # View profile
├── edit-profile.php    # Edit profile (6 tabs)
├── matches.php         # Matched profiles
├── chat.php            # Chat interface
├── settings.php        # Privacy & account settings
├── notifications.php   # User notifications
├── subscription.php    # Premium plans & payment
├── success-stories.php # Success stories page
└── my-profile.php      # Redirect to own profile
```

## Setup Instructions

### Prerequisites

- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache with `mod_rewrite` enabled
- XAMPP / WAMP / LAMP / MAMP (for local development)

### Installation

1. **Clone the repository:**
   ```bash
   git clone <repository-url>
   ```

2. **Place in web server directory:**
   - XAMPP: `C:\xampp\htdocs\matrimonial-shadi\`
   - WAMP: `C:\wamp64\www\matrimonial-shadi\`

3. **Create the database:**
   - Open phpMyAdmin or MySQL CLI
   - Run `database/schema.sql` to create all tables and seed data

4. **Configure the application:**
   - Edit `config/database.php` with your MySQL credentials
   - Edit `config/app.php` and update `SITE_URL` to match your setup
   - Add your Razorpay API keys for payment integration

5. **Set file permissions (Linux/Mac):**
   ```bash
   chmod -R 755 uploads/
   ```

6. **Access the application:**
   - Frontend: `http://localhost/matrimonial-shadi/`
   - Admin Panel: `http://localhost/matrimonial-shadi/admin/`
   - Default Admin: `admin` / `admin123`

### Default Subscription Plans

| Plan     | Price     | Duration | Key Features                    |
|----------|-----------|----------|---------------------------------|
| Free     | ₹0        | 1 year   | Browse, 5 interests/day         |
| Silver   | ₹999      | 90 days  | Chat, 30 interests, search      |
| Gold     | ₹1,999    | 180 days | Priority listing, unlimited      |
| Platinum | ₹3,999    | 1 year   | Personal matchmaker, video call  |

## Security Features

- Password hashing with `bcrypt`
- CSRF token protection on all forms
- Prepared statements (PDO) preventing SQL injection
- XSS protection via `htmlspecialchars()`
- Session-based authentication
- HTTP security headers via `.htaccess`
- OTP-based phone verification
- File upload validation (type, size)

## License

This project is proprietary. All rights reserved.
