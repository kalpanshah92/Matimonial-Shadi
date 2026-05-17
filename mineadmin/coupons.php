<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
// Only super_admin can manage coupons (matches "create, monitor, delete" requirement)
if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = getDBConnection();
$adminPage = 'coupons';

// Stats
$stats = $pdo->query("
    SELECT
      COUNT(*) AS total,
      SUM(is_active = 1) AS active,
      COALESCE(SUM(redemptions_count), 0) AS redemptions
    FROM coupons
")->fetch();

$coupons = $pdo->query("
    SELECT c.*, au.username AS created_by_name
      FROM coupons c
      LEFT JOIN admin_users au ON au.id = c.created_by_admin
     ORDER BY c.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coupons | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0"><i class="bi bi-ticket-perforated me-2"></i>Coupons</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCouponModal">
            <i class="bi bi-plus-lg me-1"></i>New Coupon
        </button>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Total Coupons</div><div class="h4 mb-0"><?= (int)$stats['total'] ?></div></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Active</div><div class="h4 mb-0"><?= (int)$stats['active'] ?></div></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Total Redemptions</div><div class="h4 mb-0"><?= (int)$stats['redemptions'] ?></div></div></div></div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Discount</th>
                            <th>Gender</th>
                            <th>Validity</th>
                            <th>Redemptions</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($coupons)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No coupons yet. Click "New Coupon" to create one.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($coupons as $c): ?>
                            <tr id="coupon-row-<?= (int)$c['id'] ?>">
                                <td><code class="fw-bold"><?= htmlspecialchars($c['code'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                <td><span class="badge bg-success"><?= (int)$c['discount_percent'] ?>% off</span></td>
                                <td><?= htmlspecialchars($c['gender_restriction'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="small">
                                    <?php if ($c['valid_from'] || $c['valid_until']): ?>
                                        <?= $c['valid_from'] ? htmlspecialchars($c['valid_from']) : '—' ?>
                                        <i class="bi bi-arrow-right"></i>
                                        <?= $c['valid_until'] ? htmlspecialchars($c['valid_until']) : '—' ?>
                                    <?php else: ?>
                                        <span class="text-muted">No expiry</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= (int)$c['redemptions_count'] ?> /
                                    <?= $c['max_redemptions'] !== null ? (int)$c['max_redemptions'] : '∞' ?>
                                </td>
                                <td>
                                    <?php if ($c['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted">
                                    <?= date('d M Y', strtotime($c['created_at'])) ?><br>
                                    <?= htmlspecialchars($c['created_by_name'] ?? 'admin') ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary btn-toggle-coupon" data-id="<?= (int)$c['id'] ?>" data-active="<?= (int)$c['is_active'] ?>">
                                        <?= $c['is_active'] ? '<i class="bi bi-pause"></i> Deactivate' : '<i class="bi bi-play"></i> Activate' ?>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger btn-delete-coupon" data-id="<?= (int)$c['id'] ?>" data-code="<?= htmlspecialchars($c['code'], ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create Coupon Modal -->
<div class="modal fade" id="createCouponModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Coupon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="couponForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Code <span class="text-danger">*</span></label>
                        <input name="code" class="form-control text-uppercase" maxlength="40" required placeholder="e.g. LAUNCH50">
                        <div class="form-text">Letters, numbers, dashes and underscores only.</div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Discount % <span class="text-danger">*</span></label>
                            <input name="discount_percent" type="number" min="1" max="100" class="form-control" required>
                            <div class="form-text">100 = fully free (skips Razorpay).</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gender Restriction</label>
                            <select name="gender_restriction" class="form-select">
                                <option value="any">Any</option>
                                <option value="Male">Male only</option>
                                <option value="Female">Female only</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Max Redemptions</label>
                        <input name="max_redemptions" type="number" min="1" class="form-control" placeholder="Leave blank for unlimited">
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Valid From</label>
                            <input name="valid_from" type="date" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Valid Until</label>
                            <input name="valid_until" type="date" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes (admin-only)</label>
                        <input name="notes" class="form-control" maxlength="255">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
$(function () {
    $('#couponForm').on('submit', function (e) {
        e.preventDefault();
        var fd = $(this).serializeArray();
        fd.push({ name: 'action', value: 'create' });
        $.post('api/coupons.php', $.param(fd), function (r) {
            if (r.success) location.reload();
            else alert(r.message || 'Failed to create coupon.');
        }, 'json').fail(function () { alert('Request failed.'); });
    });

    $(document).on('click', '.btn-toggle-coupon', function () {
        var id = $(this).data('id');
        var active = $(this).data('active');
        $.post('api/coupons.php', { action: 'toggle', id: id, is_active: active ? 0 : 1 }, function (r) {
            if (r.success) location.reload();
            else alert(r.message || 'Failed.');
        }, 'json');
    });

    $(document).on('click', '.btn-delete-coupon', function () {
        var id = $(this).data('id');
        var code = $(this).data('code');
        if (!confirm('Delete coupon "' + code + '"? Existing redemption history will be preserved but the code cannot be applied again.')) return;
        $.post('api/coupons.php', { action: 'delete', id: id }, function (r) {
            if (r.success) $('#coupon-row-' + id).remove();
            else alert(r.message || 'Failed.');
        }, 'json');
    });
});
</script>
</body>
</html>
