<?php
// check.php - System check: shows exactly what is OK or wrong on this computer. Works even if the database is broken.
define('PP_INSTALLER', true);
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/errors.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/schema.php';
if (!pp_is_local()) { pp_error_page('System check is locked', 'For safety this page only works from the computer that runs the server (localhost).'); }

$rows = [];
function chk(&$rows, $name, $ok, $detail = '', $fix = '') { $rows[] = [$name, $ok, $detail, $fix]; }

chk($rows, 'PHP version', version_compare(PHP_VERSION, '7.4.0', '>='), PHP_VERSION, 'Use XAMPP with PHP 7.4 or newer.');
foreach (['pdo_mysql' => 'PDO MySQL driver', 'mbstring' => 'mbstring', 'fileinfo' => 'fileinfo (upload check)', 'session' => 'sessions', 'json' => 'json'] as $ext => $label) {
    chk($rows, 'Extension: ' . $label, extension_loaded($ext), extension_loaded($ext) ? 'loaded' : 'NOT loaded',
        'In XAMPP: Config button of Apache -> php.ini -> remove the ; before extension=' . $ext . ', then restart Apache.');
}
$up = dirname(__FILE__) . '/uploads/designs';
if (!is_dir($up)) { @mkdir($up, 0755, true); }
chk($rows, 'Upload folder writable', is_dir($up) && is_writable($up), $up, 'Make sure the folder uploads/designs exists and is writable.');
$lg = dirname(__FILE__) . '/logs';
if (!is_dir($lg)) { @mkdir($lg, 0755, true); }
chk($rows, 'Logs folder writable', is_dir($lg) && is_writable($lg), $lg, 'Create the folder logs/.');
chk($rows, 'Project location', is_file(__DIR__ . '/includes/bootstrap.php'), pp_base_url_guess() === '' ? '(web root)' : pp_base_url_guess(),
    'index.php must be directly inside htdocs\\online-printing-system (not in a folder inside a folder).');
chk($rows, 'upload_max_filesize', true, ini_get('upload_max_filesize') . ' / post_max_size ' . ini_get('post_max_size'));

$dbok = false; $problems = [];
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $dbok = true;
    chk($rows, 'MySQL connection', true, "connected to database '$db'");
    $problems = pp_schema_problems($pdo, $db);
    chk($rows, 'Database tables and columns', !$problems, $problems ? implode('; ', array_map(function ($t, $c) { return "$t (" . implode(', ', $c) . ')'; }, array_keys($problems), $problems)) : 'all 13 tables are present',
        'Open install.php and click "Repair the database".');
    if (!$problems) {
        $n = []; foreach (['users', 'services', 'orders', 'vendor_orders'] as $t) { $n[] = $t . ': ' . (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn(); }
        chk($rows, 'Data', true, implode(', ', $n));
        $pend = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
        chk($rows, 'Admin account exists', $pend > 0, $pend . ' admin(s)', 'Open install.php and click "Repair the database".');
    }
} catch (Throwable $e) {
    $code = (int)$e->getCode();
    $fix = $code === 1049 ? 'The database does not exist: open install.php.' : ($code === 2002 ? 'MySQL is not running: start MySQL in the XAMPP Control Panel.' : 'Check the settings in config/db.php.');
    chk($rows, 'MySQL connection', false, $e->getMessage(), $fix);
}
$logTail = '';
$lf = $lg . '/php-error.log';
if (is_file($lf)) { $lines = file($lf, FILE_IGNORE_NEW_LINES); $logTail = implode("\n", array_slice($lines, -15)); }
$base = pp_base_url_guess();
$bad = count(array_filter($rows, function ($r) { return !$r[1]; }));
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>System check - <?= htmlspecialchars(APP_NAME) ?></title><link href="assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><div class="container py-4" style="max-width:860px">
<h2 class="mb-3">PrintPack system check</h2>
<div class="alert alert-<?= $bad ? 'danger' : 'success' ?>"><?= $bad ? "$bad problem(s) found. Read the red rows below." : 'Everything looks good.' ?></div>
<div class="card shadow-sm mb-3"><table class="table mb-0 align-middle"><thead><tr><th>Check</th><th>Result</th><th>Details / how to fix</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr class="<?= $r[1] ? '' : 'table-danger' ?>"><td><?= htmlspecialchars($r[0]) ?></td>
<td><?= $r[1] ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">PROBLEM</span>' ?></td>
<td class="small"><?= htmlspecialchars($r[2]) ?><?php if (!$r[1] && $r[3]): ?><div class="text-danger mt-1"><b>Fix:</b> <?= htmlspecialchars($r[3]) ?></div><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<a class="btn btn-primary" href="<?= htmlspecialchars($base) ?>/index.php">Open the website</a>
<a class="btn btn-outline-secondary" href="<?= htmlspecialchars($base) ?>/install.php<?= $problems ? '?mode=repair' : '' ?>">Installer / repair</a>
<?php if ($logTail): ?><h5 class="mt-4">Last lines of logs/php-error.log</h5><pre class="bg-white border p-2 small" style="white-space:pre-wrap"><?= htmlspecialchars($logTail) ?></pre><?php endif; ?>
</div></body></html>
