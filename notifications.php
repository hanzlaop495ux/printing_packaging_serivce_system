<?php
// notifications.php - Notification centre for all roles
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$uid = $_SESSION['user_id'];

// Clicking a notification marks it as read and opens its link
if (isset($_GET['go'])) {
    $st = $pdo->prepare("SELECT * FROM notifications WHERE id = ? AND user_id = ?");
    $st->execute([(int)$_GET['go'], $uid]);
    if ($n = $st->fetch()) {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$n['id']]);
        redirect($n['link'] ?: 'notifications.php');
    }
    redirect('notifications.php');
}
if (is_post()) {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'read_all') {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$uid]);
        flash('success', 'All notifications marked as read.');
    }
    redirect('notifications.php');
}

$st = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 100");
$st->execute([$uid]);
$items = $st->fetchAll();

$page_title = 'Notifications';
require __DIR__ . '/includes/header.php';
?>
<main class="container flex-grow-1 mb-4" style="max-width:800px">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="page-title mb-0">Notifications</h2>
        <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="read_all">
            <button class="btn btn-sm btn-outline-secondary">Mark all as read</button></form>
    </div>
    <div class="card shadow-sm">
        <ul class="list-group list-group-flush">
            <?php foreach ($items as $n): ?>
                <li class="list-group-item <?= $n['is_read'] ? '' : 'bg-light' ?>">
                    <a class="text-decoration-none text-dark d-block" href="<?= url('notifications.php?go=' . (int)$n['id']) ?>">
                        <div class="d-flex justify-content-between flex-wrap">
                            <strong><?= $n['is_read'] ? '' : '<span class="badge bg-primary me-1">New</span>' ?><?= e($n['title']) ?></strong>
                            <small class="text-muted"><?= e(date('d M Y, h:i A', strtotime($n['created_at']))) ?></small>
                        </div>
                        <div class="small text-muted"><?= e($n['message']) ?></div>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php if (!$items): ?><li class="list-group-item text-center text-muted py-4">You have no notifications.</li><?php endif; ?>
        </ul>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
