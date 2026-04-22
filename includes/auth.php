<?php
/**
 * Auth Middleware - Include this on pages that require authentication
 */
require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) {
    setFlash('error', 'Please login to access this page.');
    redirect(SITE_URL . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$currentUser = getCurrentUser();

if (!$currentUser || !$currentUser['is_active']) {
    session_destroy();
    setFlash('error', 'Your account has been deactivated. Please contact support.');
    redirect(SITE_URL . '/login.php');
}
