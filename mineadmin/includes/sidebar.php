<!-- Admin Sidebar -->
<div class="admin-sidebar" id="adminSidebar">
    <div class="text-center px-3 mb-4">
        <h5 class="text-white"><i class="bi bi-hearts me-2"></i><?= SITE_NAME ?></h5>
        <small class="text-white-50">Admin Panel</small>
    </div>
    
    <nav class="nav flex-column">
        <a class="nav-link <?= ($adminPage ?? '') === 'dashboard' ? 'active' : '' ?>" href="<?= SITE_URL ?>/mineadmin/index.php">
            <i class="bi bi-speedometer2"></i>Dashboard
        </a>
        <a class="nav-link <?= ($adminPage ?? '') === 'profiles' ? 'active' : '' ?>" href="<?= SITE_URL ?>/mineadmin/profiles.php">
            <i class="bi bi-people"></i>Manage Profiles
        </a>
        <?php if (($_SESSION['admin_role'] ?? '') === 'super_admin'): ?>
            <a class="nav-link <?= ($adminPage ?? '') === 'advertisements' ? 'active' : '' ?>" href="<?= SITE_URL ?>/mineadmin/advertisements.php">
                <i class="bi bi-image"></i>Advertisements
            </a>
        <?php endif; ?>
        <?php
            $pcPdo = getDBConnection();
            $pcCount = 0;
            try {
                $pcStmt = $pcPdo->query("SELECT COUNT(*) FROM profile_change_requests WHERE status = 'pending'");
                $pcCount = $pcStmt->fetchColumn();
                $pcStmt2 = $pcPdo->query("SELECT COUNT(*) FROM photos WHERE is_approved = 0");
                $pcCount += $pcStmt2->fetchColumn();
            } catch (Exception $e) {}
        ?>
        <a class="nav-link <?= ($adminPage ?? '') === 'profile-changes' ? 'active' : '' ?>" href="<?= SITE_URL ?>/mineadmin/profile-changes.php">
            <i class="bi bi-pencil-square"></i>Profile Changes
            <?php if ($pcCount > 0): ?>
                <span class="badge bg-danger rounded-pill ms-1"><?= $pcCount ?></span>
            <?php endif; ?>
        </a>
        <a class="nav-link <?= ($adminPage ?? '') === 'subscriptions' ? 'active' : '' ?>" href="<?= SITE_URL ?>/mineadmin/subscriptions.php">
            <i class="bi bi-credit-card"></i>Subscriptions
        </a>
        <?php if (($_SESSION['admin_role'] ?? '') === 'super_admin'): ?>
            <?php
                $drPdo = getDBConnection();
                $drCount = 0;
                try {
                    $drStmt = $drPdo->query("SELECT COUNT(*) FROM deactivation_requests WHERE status = 'pending'");
                    $drCount = $drStmt->fetchColumn();
                } catch (Exception $e) {}
            ?>
            <a class="nav-link <?= ($adminPage ?? '') === 'deactivation-requests' ? 'active' : '' ?>" href="<?= SITE_URL ?>/mineadmin/deactivation-requests.php">
                <i class="bi bi-person-x"></i>Deactivation Requests
                <?php if ($drCount > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-1"><?= $drCount ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>
        <a class="nav-link <?= ($adminPage ?? '') === 'reports' ? 'active' : '' ?>" href="<?= SITE_URL ?>/mineadmin/reports.php">
            <i class="bi bi-flag"></i>Reports
        </a>
        <a class="nav-link <?= ($adminPage ?? '') === 'stories' ? 'active' : '' ?>" href="<?= SITE_URL ?>/mineadmin/stories.php">
            <i class="bi bi-heart"></i>Success Stories
        </a>
        <a class="nav-link <?= ($adminPage ?? '') === 'plans' ? 'active' : '' ?>" href="<?= SITE_URL ?>/mineadmin/plans.php">
            <i class="bi bi-star"></i>Plans & Pricing
        </a>
        <a class="nav-link <?= ($adminPage ?? '') === 'analytics' ? 'active' : '' ?>" href="<?= SITE_URL ?>/mineadmin/analytics.php">
            <i class="bi bi-graph-up"></i>Analytics
        </a>
        <hr class="border-light mx-3">
        <a class="nav-link <?= ($adminPage ?? '') === 'change-password' ? 'active' : '' ?>" href="<?= SITE_URL ?>/mineadmin/change-password.php">
            <i class="bi bi-key"></i>Change Password
        </a>
        <a class="nav-link" href="<?= SITE_URL ?>" target="_blank">
            <i class="bi bi-globe"></i>View Website
        </a>
        <a class="nav-link text-danger" href="<?= SITE_URL ?>/mineadmin/logout.php">
            <i class="bi bi-box-arrow-right"></i>Logout
        </a>
    </nav>
</div>
