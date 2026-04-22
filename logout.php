<?php
require_once __DIR__ . '/includes/functions.php';

// Destroy session
session_unset();
session_destroy();

// Clear remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

setFlash('success', 'You have been logged out successfully.');
redirect(SITE_URL . '/login.php');
