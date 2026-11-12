<?php
// search.php - Global search (vendors, services/designs, orders) - results depend on the user's role
require_once __DIR__ . '/includes/bootstrap.php';

$q = trim($_GET['q'] ?? '');
$role = $_SESSION['role'] ?? 'guest';
$uid  = $_SESSION['user_id'] ?? 0;
$like = "%$q%";
$res = ['services' => [], 'vendors' => [], 'orders' => [], 'designs' => [], 'users' => []];

if (mb_strlen($q) >= 2) {
    // Services (everyone) - vendors only see active vendors' services unless it is their own
    $st = $pdo->prepare("SELECT s.id, s.title, s.price, s.unit_label, u.name AS vendor_name, c.name AS category_name
                         FROM services s JOIN users u ON s.vendor_id = u.id LEFT JOIN categories c ON s.category_id = c.id
                         WHERE u.status = 'active' AND (s.title LIKE ? OR s.description LIKE ? OR s.material LIKE ? OR c.name LIKE ?)
                         ORDER BY s.id DESC LIMIT 20");
    $st->execute([$like, $like, $like, $like]);
    $res['services'] = $st->fetchAll();

    // Vendors (everyone)
    $st = $pdo->prepare("SELECT u.id, u.name, u.address, (SELECT COUNT(*) FROM services s WHERE s.vendor_id = u.id AND s.is_available = 1) AS svc
                         FROM users u WHERE u.role = 'vendor' AND u.status = 'active' AND (u.name LIKE ? OR u.address LIKE ?) LIMIT 20");
    $st->execute([$like, $like]);
    $res['vendors'] = $st->fetchAll();

    // Orders
    $num = ltrim($q, '#');
    $id_match = ctype_digit($num) ? (int)$num : -1;
    if ($role === 'customer') {
        $st = $pdo->prepare("SELECT DISTINCT o.id, o.status, o.total, o.created_at FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.id
                             WHERE o.customer_id = ? AND (o.id = ? OR oi.service_title LIKE ? OR o.delivery_address LIKE ?) ORDER BY o.id DESC LIMIT 20");
        $st->execute([$uid, $id_match, $like, $like]);
        $res['orders'] = $st->fetchAll();
        $st = $pdo->prepare("SELECT id, original_name, created_at FROM designs WHERE user_id = ? AND original_name LIKE ? ORDER BY id DESC LIMIT 20");
        $st->execute([$uid, $like]);
        $res['designs'] = $st->fetchAll();
    } elseif ($role === 'vendor') {
        $st = $pdo->prepare("SELECT DISTINCT vo.id, vo.order_id, vo.status, vo.total, u.name AS customer_name FROM vendor_orders vo
                             JOIN orders o ON vo.order_id = o.id JOIN users u ON o.customer_id = u.id LEFT JOIN order_items oi ON oi.vendor_order_id = vo.id
                             WHERE vo.vendor_id = ? AND (vo.id = ? OR vo.order_id = ? OR u.name LIKE ? OR oi.service_title LIKE ?) ORDER BY vo.id DESC LIMIT 20");
        $st->execute([$uid, $id_match, $id_match, $like, $like]);
        $res['orders'] = $st->fetchAll();
        $st = $pdo->prepare("SELECT DISTINCT d.id, d.original_name, d.created_at FROM designs d
                             JOIN order_items oi ON oi.design_id = d.id JOIN vendor_orders vo ON oi.vendor_order_id = vo.id
                             WHERE vo.vendor_id = ? AND d.original_name LIKE ? LIMIT 20");
        $st->execute([$uid, $like]);
        $res['designs'] = $st->fetchAll();
    } elseif ($role === 'admin') {
        $st = $pdo->prepare("SELECT DISTINCT o.id, o.status, o.total, o.created_at, u.name AS customer_name FROM orders o JOIN users u ON o.customer_id = u.id
                             LEFT JOIN order_items oi ON oi.order_id = o.id WHERE o.id = ? OR u.name LIKE ? OR oi.service_title LIKE ? ORDER BY o.id DESC LIMIT 20");
        $st->execute([$id_match, $like, $like]);
        $res['orders'] = $st->fetchAll();
        $st = $pdo->prepare("SELECT d.id, d.original_name, d.created_at, u.name AS owner FROM designs d JOIN users u ON d.user_id = u.id WHERE d.original_name LIKE ? LIMIT 20");
        $st->execute([$like]);
        $res['designs'] = $st->fetchAll();
        $st = $pdo->prepare("SELECT id, name, email, role, status FROM users WHERE name LIKE ? OR email LIKE ? LIMIT 20");
        $st->execute([$like, $like]);
        $res['users'] = $st->fetchAll();
    }
}
$order_link = function ($o) use ($role) {
    if ($role === 'admin') return url('admin/order-details.php?id=' . $o['id']);
    if ($role === 'vendor') return url('vendor/order-details.php?id=' . $o['id']);
    return url('customer/order-details.php?id=' . $o['id']);
};

$page_title = 'Search';
require __DIR__ . '/includes/header.php';
?>
<main class="container flex-grow-1 mb-4">
    <h2 class="page-title mb-3">Search</h2>
    <form method="GET" class="input-group mb-4">
        <input type="search" name="q" class="form-control" placeholder="Search vendors, services, designs, orders..." value="<?= e($q) ?>" minlength="2" required>
        <button class="btn btn-primary">Search</button>
    </form>
    <?php if ($q !== '' && mb_strlen($q) < 2): ?><div class="alert alert-info">Please type at least 2 characters.</div><?php endif; ?>

    <?php if (mb_strlen($q) >= 2): ?>
        <?php $total = array_sum(array_map('count', $res)); ?>
        <p class="text-muted"><?= $total ?> result<?= $total == 1 ? '' : 's' ?> for "<?= e($q) ?>"</p>

        <?php if ($res['services']): ?><h5>Services</h5>
            <div class="list-group mb-4">
                <?php foreach ($res['services'] as $s): ?>
                    <a class="list-group-item list-group-item-action" href="<?= url('customer/service-details.php?id=' . (int)$s['id']) ?>">
                        <strong><?= e($s['title']) ?></strong> <span class="badge bg-info text-dark"><?= e($s['category_name'] ?? 'General') ?></span>
                        <div class="small text-muted">by <?= e($s['vendor_name']) ?> &middot; <?= money($s['price']) ?> / <?= e($s['unit_label']) ?></div>
                    </a>
                <?php endforeach; ?></div><?php endif; ?>

        <?php if ($res['vendors']): ?><h5>Vendors</h5>
            <div class="list-group mb-4">
                <?php foreach ($res['vendors'] as $v): ?>
                    <a class="list-group-item list-group-item-action" href="<?= url('customer/vendor-profile.php?id=' . (int)$v['id']) ?>">
                        <strong><?= e($v['name']) ?></strong> <span class="badge bg-success"><?= (int)$v['svc'] ?> services</span>
                        <div class="small text-muted"><?= e($v['address'] ?? '') ?></div></a>
                <?php endforeach; ?></div><?php endif; ?>

        <?php if ($res['orders']): ?><h5>Orders</h5>
            <div class="list-group mb-4">
                <?php foreach ($res['orders'] as $o): ?>
                    <a class="list-group-item list-group-item-action" href="<?= $order_link($o) ?>">
                        <strong>Order <?= order_ref($role === 'vendor' ? $o['order_id'] : $o['id']) ?></strong> <?= status_badge($o['status']) ?>
                        <span class="small text-muted"> &middot; <?= money($o['total']) ?><?= isset($o['customer_name']) ? ' &middot; ' . e($o['customer_name']) : '' ?></span></a>
                <?php endforeach; ?></div><?php endif; ?>

        <?php if ($res['designs']): ?><h5>Designs</h5>
            <div class="list-group mb-4">
                <?php foreach ($res['designs'] as $d): ?>
                    <a class="list-group-item list-group-item-action" href="<?= url('download.php?id=' . (int)$d['id']) ?>" target="_blank">
                        <?= e($d['original_name']) ?> <span class="small text-muted"><?= isset($d['owner']) ? '&middot; ' . e($d['owner']) : '' ?></span></a>
                <?php endforeach; ?></div><?php endif; ?>

        <?php if ($res['users']): ?><h5>Users</h5>
            <div class="list-group mb-4">
                <?php foreach ($res['users'] as $u): ?>
                    <a class="list-group-item list-group-item-action" href="<?= url('admin/' . ($u['role'] === 'vendor' ? 'vendors' : 'users') . '.php?q=' . urlencode($u['email'])) ?>">
                        <?= e($u['name']) ?> <small class="text-muted">&middot; <?= e($u['email']) ?> &middot; <?= e($u['role']) ?></small> <?= status_badge($u['status']) ?></a>
                <?php endforeach; ?></div><?php endif; ?>

        <?php if (!$total): ?><div class="alert alert-info">No results found. Try a different keyword.</div><?php endif; ?>
        <?php if (!is_logged_in()): ?><p class="small text-muted">Log in to also search your orders and designs.</p><?php endif; ?>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
