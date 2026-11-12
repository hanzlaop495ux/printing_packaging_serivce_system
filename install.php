<?php
// install.php - Installer / repair tool. Creates or repairs the database from your browser (no phpMyAdmin needed).
// Only works from the same computer (localhost). You may delete this file after the setup is done.
define('PP_INSTALLER', true);
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/errors.php';
pp_setup_errors();
require_once __DIR__ . '/config/db.php';          // gives $host $db $user $pass $charset
require_once __DIR__ . '/includes/schema.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!pp_is_local()) { pp_error_page('Installer is locked', 'For safety the installer only works from the computer that runs the server (localhost).'); }
if (empty($_SESSION['inst_token'])) { $_SESSION['inst_token'] = bin2hex(random_bytes(16)); }

$log = []; $ok = null; $mode = $_GET['mode'] ?? 'fresh';
$sqlFile = __DIR__ . '/database/printing_system.sql';

function inst_connect($host, $user, $pass, $charset, $db = null) {
    $dsn = "mysql:host=$host;charset=$charset" . ($db ? ";dbname=$db" : '');
    return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($_SESSION['inst_token'], $_POST['token'] ?? '')) { pp_error_page('Invalid form', 'Please go back and try again.'); }
    $action = $_POST['action'] ?? '';
    try {
        if (!is_file($sqlFile)) { throw new Exception('database/printing_system.sql is missing from the project folder.'); }
        $statements = pp_split_sql(file_get_contents($sqlFile));
        $pdo = inst_connect($host, $user, $pass, $charset);

        if ($action === 'fresh') {
            if (trim($_POST['confirm'] ?? '') !== 'RESET') { throw new Exception('Type RESET in the box to confirm. Nothing was changed.'); }
            $n = 0;
            foreach ($statements as $st) { $pdo->exec($st); $n++; }
            $log[] = "Executed $n SQL statements. Database '$db' created with sample data.";
            $_SESSION['schema_ok'] = null;
            $ok = true;
        } elseif ($action === 'repair') {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            $pdo = inst_connect($host, $user, $pass, $charset, $db);
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            $creates = [];
            foreach ($statements as $st) {
                if (preg_match('/^CREATE TABLE\s+`?(\w+)`?/i', $st, $m)) { $creates[strtolower($m[1])] = $st; }
            }
            $addable = pp_addable_columns();
            // create tables in a safe order: parents first
            $problems = pp_schema_problems($pdo, $db);
            $order = array_keys(pp_required_schema());
            foreach ($order as $t) {
                if (!isset($problems[$t])) continue;
                if ($problems[$t] === ['table is missing']) {
                    $pdo->exec($creates[$t]); $log[] = "Created table $t.";
                    continue;
                }
                $cols = $problems[$t];
                $canAdd = isset($addable[$t]) && !array_diff($cols, array_keys($addable[$t]));
                if ($canAdd) {
                    foreach ($cols as $c) { $pdo->exec("ALTER TABLE `$t` ADD COLUMN `$c` " . $addable[$t][$c]); $log[] = "Added column $t.$c."; }
                } else {
                    $bak = 'bak_' . $t . '_' . date('YmdHis');
                    $pdo->exec("RENAME TABLE `$t` TO `$bak`");
                    $pdo->exec($creates[$t]);
                    $log[] = "Table $t had an old structure (missing: " . implode(', ', $cols) . "). Old data kept in `$bak`, new table created.";
                }
            }
            // default data
            foreach (['delivery_base_fee' => '100', 'delivery_rate_per_km' => '15', 'default_distance_km' => '5'] as $k => $v) {
                $pdo->prepare("INSERT IGNORE INTO settings (key_name, value) VALUES (?, ?)")->execute([$k, $v]);
            }
            if (!(int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn()) {
                foreach ([['Banners','Printing'],['Brochures','Printing'],['Business Cards','Printing'],['Flyers & Posters','Printing'],
                          ['Stickers & Labels','Printing'],['Boxes','Packaging'],['Packaging Bags','Packaging'],['Tape & Wrapping','Packaging']] as $c) {
                    $pdo->prepare("INSERT INTO categories (name, type) VALUES (?, ?)")->execute($c);
                }
                $log[] = 'Added the default service categories.';
            }
            if (!(int)$pdo->query("SELECT COUNT(*) FROM service_types")->fetchColumn()) {
                foreach (pp_default_service_types() as $i => $t) {
                    $pdo->prepare("INSERT INTO service_types (name, type, sort_order) VALUES (?, ?, ?)")->execute([$t[0], $t[1], $i + 1]);
                }
                $log[] = 'Added the predefined vendor service types.';
            }
            $hasAdmin = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            if (!$hasAdmin) {
                $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES ('System Admin', 'admin@printpack.com', ?, 'admin', 'active')")
                    ->execute([password_hash('password123', PASSWORD_DEFAULT)]);
                $log[] = 'No admin existed: created admin@printpack.com with password password123.';
            }
            // The old project's demo accounts were saved with a password hash that never matched "password123" - fix them
            $demo = $pdo->query("SELECT id, email, password FROM users WHERE email IN ('admin@printpack.com','vendor@printpack.com','customer@printpack.com')")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($demo as $d) {
                if (!password_verify('password123', $d['password'])) {
                    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash('password123', PASSWORD_DEFAULT), $d['id']]);
                    $log[] = 'Reset the password of ' . $d['email'] . ' to: password123';
                }
            }
            $pdo->exec("INSERT INTO admins (user_id) SELECT u.id FROM users u WHERE u.role = 'admin' AND u.id NOT IN (SELECT user_id FROM admins)");
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            $log[] = 'Repair finished. Your existing users were kept.';
            $_SESSION['schema_ok'] = null;
            $ok = true;
        }
    } catch (Throwable $e) {
        $ok = false; $log[] = 'ERROR: ' . $e->getMessage();
    }
}

// current state
$state = 'Could not check';
try {
    $p = inst_connect($host, $user, $pass, $charset, $db);
    $pr = pp_schema_problems($p, $db);
    $state = $pr ? 'Database exists but is out of date: ' . implode('; ', array_map(function ($t, $c) { return "$t (" . implode(', ', $c) . ')'; }, array_keys($pr), $pr)) : 'Database is complete and up to date.';
} catch (Throwable $e) {
    $state = ((int)$e->getCode() === 1049) ? "Database '$db' does not exist yet." : 'Cannot connect to MySQL: ' . $e->getMessage();
}
$base = pp_base_url_guess();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installer - <?= htmlspecialchars(APP_NAME) ?></title>
<link href="assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><div class="container py-4" style="max-width:760px">
<h2 class="mb-3">PrintPack installer / repair</h2>
<div class="alert alert-secondary"><strong>Current state:</strong> <?= htmlspecialchars($state) ?></div>
<?php if ($ok === true): ?><div class="alert alert-success"><strong>Done!</strong><ul class="mb-2"><?php foreach ($log as $l): ?><li><?= htmlspecialchars($l) ?></li><?php endforeach; ?></ul>
    <a class="btn btn-success" href="<?= htmlspecialchars($base) ?>/index.php">Open the website</a>
    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($base) ?>/check.php">System check</a></div>
<?php elseif ($ok === false): ?><div class="alert alert-danger"><?php foreach ($log as $l): ?><div><?= htmlspecialchars($l) ?></div><?php endforeach; ?></div><?php endif; ?>

<div class="card shadow-sm mb-3"><div class="card-body">
  <h5>Option A - Repair / upgrade <span class="badge bg-success">keeps your data</span></h5>
  <p class="small text-muted">Use this if you already had the older project database. It adds the missing tables and columns, creates default categories/settings, and links admin accounts. Old tables with an incompatible structure are kept as <code>bak_...</code> backups.</p>
  <form method="POST"><input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['inst_token']) ?>"><input type="hidden" name="action" value="repair">
    <button class="btn btn-success">Repair the database</button></form>
</div></div>

<div class="card shadow-sm mb-3 border-danger"><div class="card-body">
  <h5>Option B - Fresh install with sample data <span class="badge bg-danger">deletes existing data</span></h5>
  <p class="small text-muted">Creates the database <code><?= htmlspecialchars($db) ?></code> and all 13 tables, with demo users, vendors, services and orders. Test accounts use the password <code>password123</code>.</p>
  <form method="POST" class="row g-2"><input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['inst_token']) ?>"><input type="hidden" name="action" value="fresh">
    <div class="col-sm-6"><input name="confirm" class="form-control" placeholder="Type RESET to confirm" autocomplete="off"></div>
    <div class="col-sm-6"><button class="btn btn-danger w-100">Install fresh database</button></div></form>
</div></div>
<p class="small text-muted">Settings used: host <code><?= htmlspecialchars($host) ?></code>, user <code><?= htmlspecialchars($user) ?></code>, database <code><?= htmlspecialchars($db) ?></code> (edit in <code>config/db.php</code>). After the setup you may delete <code>install.php</code>.</p>
</div></body></html>
