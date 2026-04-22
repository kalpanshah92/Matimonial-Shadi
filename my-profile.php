<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/includes/auth.php';

// Redirect to own profile page
redirect(SITE_URL . '/profile.php?id=' . $currentUser['id']);
