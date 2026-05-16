<?php
require_once __DIR__ . '/includes/functions.php';

// F-09 Invalidate remember-me selector server-side
clearRememberToken();

// Clear any legacy cookie name
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Destroy session (do BEFORE setFlash so we get a clean new session for the flash)
session_unset();
session_destroy();
session_start();
session_regenerate_id(true);

setFlash('success', 'You have been logged out successfully.');
redirect(SITE_URL . '/login.php');
