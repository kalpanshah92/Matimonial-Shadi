<?php
require_once __DIR__ . '/functions.php';

$currentUser = getCurrentUser();
$notifCount = $currentUser ? getUnreadNotificationCount($currentUser['id']) : 0;
$topNotifications = $currentUser ? getTopNotifications($currentUser['id'], 3) : [];
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' | ' . SITE_NAME : SITE_NAME ?></title>
    
    <!-- System Font (SF Pro style) -->
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- Animate.css -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
    
    <?php if (isset($extraCSS)): ?>
        <?php foreach ($extraCSS as $css): ?>
            <link href="<?= $css ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>

<!-- Top Bar -->
<div class="top-bar d-none d-md-block">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <span class="me-3"><i class="bi bi-envelope me-1"></i> <?= SITE_EMAIL ?></span>
                <span><i class="bi bi-telephone me-1"></i> <?= SITE_PHONE ?></span>
            </div>
            <div class="col-md-6 text-end">
                <?php if ($currentUser): ?>
                    <span class="me-3">Welcome, <strong><?= sanitize($currentUser['name']) ?></strong></span>
                <?php else: ?>
                    <a href="<?= SITE_URL ?>/login.php" class="text-white me-3"><i class="bi bi-box-arrow-in-right me-1"></i>Login</a>
                    <a href="<?= SITE_URL ?>/register.php" class="text-white"><i class="bi bi-person-plus me-1"></i>Register Free</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark main-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand" href="<?= SITE_URL ?>">
            <i class="bi bi-hearts"></i>
            <span class="brand-text"><?= SITE_NAME ?></span>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'index' ? 'active' : '' ?>" href="<?= SITE_URL ?>">
                        <i class="bi bi-house me-1"></i>Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'search' ? 'active' : '' ?>" href="<?= SITE_URL ?>/search.php">
                        <i class="bi bi-search me-1"></i>Search
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'success-stories' ? 'active' : '' ?>" href="<?= SITE_URL ?>/success-stories.php">
                        <i class="bi bi-heart me-1"></i>Success Story
                    </a>
                </li>
                
                <?php if ($currentUser): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="<?= SITE_URL ?>/dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'matches' ? 'active' : '' ?>" href="<?= SITE_URL ?>/matches.php">
                            <i class="bi bi-heart me-1"></i>Matches
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'chat' ? 'active' : '' ?>" href="<?= SITE_URL ?>/chat.php">
                            <i class="bi bi-chat-dots me-1"></i>Chat
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <span class="position-relative">
                                <i class="bi bi-bell me-1"></i>
                                <?php if ($notifCount > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger badge-sm">
                                        <?= $notifCount > 99 ? '99+' : $notifCount ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end notification-dropdown">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <?php if (!empty($topNotifications)): ?>
                                <?php foreach ($topNotifications as $notif): ?>
                                    <?php
                                    $viewLink = $notif['link'] ?? 'notifications.php';
                                    // Override link for interest type to go to dashboard
                                    if ($notif['type'] === 'interest') {
                                        $viewLink = 'dashboard.php';
                                    }
                                    ?>
                                    <li>
                                        <a class="dropdown-item <?= $notif['is_read'] ? 'text-muted' : '' ?>" href="<?= SITE_URL ?>/<?= $viewLink ?>">
                                            <div class="d-flex align-items-start">
                                                <div class="flex-grow-1">
                                                    <div class="fw-semibold"><?= htmlspecialchars($notif['title']) ?></div>
                                                    <div class="small text-muted"><?= htmlspecialchars(substr($notif['message'], 0, 50)) ?><?= strlen($notif['message']) > 50 ? '...' : '' ?></div>
                                                    <div class="small text-muted" style="font-size: 11px;"><?= timeAgo($notif['created_at']) ?></div>
                                                </div>
                                                <?php if (!$notif['is_read']): ?>
                                                    <span class="badge bg-primary ms-2" style="font-size: 10px;">New</span>
                                                <?php endif; ?>
                                            </div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                                <li><hr class="dropdown-divider"></li>
                            <?php else: ?>
                                <li><a class="dropdown-item text-muted" href="<?= SITE_URL ?>/notifications.php">No notifications yet</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item text-primary fw-semibold" href="<?= SITE_URL ?>/notifications.php">View All Notifications</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <img src="<?= getProfilePic($currentUser['profile_pic'], $currentUser['gender']) ?>" 
                                 class="rounded-circle me-1" width="28" height="28" alt="Profile">
                            <?= sanitize($currentUser['name']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= SITE_URL ?>/my-profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                            <li><a class="dropdown-item" href="<?= SITE_URL ?>/edit-profile.php"><i class="bi bi-pencil-square me-2"></i>Edit Profile</a></li>
                            <li><a class="dropdown-item" href="<?= SITE_URL ?>/settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><a class="dropdown-item" href="<?= SITE_URL ?>/subscription.php"><i class="bi bi-star me-2"></i>Upgrade Plan</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= SITE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= SITE_URL ?>/subscription.php">
                            <i class="bi bi-star me-1"></i>Plans
                        </a>
                    </li>
                    <li class="nav-item d-lg-none">
                        <a class="nav-link" href="<?= SITE_URL ?>/login.php">Login</a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-accent btn-sm px-3" href="<?= SITE_URL ?>/register.php">
                            Register Free
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Flash Messages -->
<?php $flash = getFlash(); ?>
<?php if ($flash): ?>
<div class="container mt-3">
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show" role="alert">
        <?= $flash['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php endif; ?>

<main>
