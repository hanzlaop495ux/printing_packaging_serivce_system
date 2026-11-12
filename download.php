<?php
// download.php - Secure access to uploaded design files (only the owner, the vendor of the order, or admin)
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT * FROM designs WHERE id = ?");
$st->execute([$id]);
$d = $st->fetch();

$allowed = false;
if ($d) {
    if ($_SESSION['role'] === 'admin' || (int)$d['user_id'] === (int)$_SESSION['user_id']) {
        $allowed = true;
    } elseif ($_SESSION['role'] === 'vendor') {
        $c = $pdo->prepare("SELECT COUNT(*) FROM order_items oi JOIN vendor_orders vo ON oi.vendor_order_id = vo.id
                            WHERE oi.design_id = ? AND vo.vendor_id = ?");
        $c->execute([$id, $_SESSION['user_id']]);
        $allowed = $c->fetchColumn() > 0;
    }
}
$path = $d ? UPLOAD_DIR . $d['stored_name'] : '';
if (!$allowed || !is_file($path)) {
    http_response_code(404);
    die('File not found.');
}
$disposition = isset($_GET['dl']) ? 'attachment' : 'inline';
header('Content-Type: ' . $d['mime']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $d['original_name']) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
