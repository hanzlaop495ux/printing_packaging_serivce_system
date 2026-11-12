<?php
// contact.php - Public contact / support form.
// Also used by SUSPENDED accounts (?type=suspension): the person must have entered the correct password on the login page,
// so the email shown here is proven to belong to them. Nobody is logged in by this page.
require_once __DIR__ . '/includes/bootstrap.php';

$ctx = $_SESSION['suspended_contact'] ?? null;
if ($ctx && (time() - (int)($ctx['time'] ?? 0)) > 1800) { unset($_SESSION['suspended_contact']); $ctx = null; }   // expires after 30 minutes
$mode = (($_GET['type'] ?? $_POST['type'] ?? '') === 'suspension') ? 'suspension' : 'general';
if ($mode === 'suspension' && !$ctx) {
    flash('info', 'To contact the administrator about a suspended account, first try to log in with your email and password so we can identify your account. '
                . 'If you cannot do that, use this general contact form.');
    redirect('contact.php');
}
$me = is_logged_in() ? current_user_row() : null;

$errors = [];
$name    = $mode === 'suspension' ? $ctx['name']  : ($me['name']  ?? '');
$email   = $mode === 'suspension' ? $ctx['email'] : ($me['email'] ?? '');
$subject = $mode === 'suspension' ? 'Account Suspension' : '';
$message = '';

if (is_post()) {
    csrf_verify();
    if (trim($_POST['website'] ?? '') !== '') {            // honeypot field: only bots fill it in
        flash('success', 'Your request has been sent.'); redirect($mode === 'suspension' ? 'login.php' : 'contact.php');
    }
    $message = trim($_POST['message'] ?? '');
    if ($mode === 'general') {
        $name    = $me ? $me['name'] : trim($_POST['name'] ?? '');
        $email   = $me ? $me['email'] : trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
    }
    // server-side validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) { $errors[] = 'Please enter a valid email address.'; }
    if ($mode === 'general' && (mb_strlen($subject) < 3 || mb_strlen($subject) > 150)) { $errors[] = 'Please enter a subject (3 to 150 characters).'; }
    if (mb_strlen($name) > 100) { $errors[] = 'The name is too long.'; }
    if ($message === '') { $errors[] = 'Please write your message.'; }
    elseif (mb_strlen($message) < 10) { $errors[] = 'Your message is too short. Please explain the issue in at least 10 characters.'; }
    elseif (mb_strlen($message) > 2000) { $errors[] = 'Your message is too long (maximum 2000 characters).'; }

    $ip_hash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|pp-support');
    $msg_hash = hash('sha256', mb_strtolower($email . '|' . $message));
    if (!$errors) {                                         // abuse / duplicate protection
        $c = $pdo->prepare("SELECT COUNT(*) FROM support_requests WHERE message_hash = ? AND created_at > (NOW() - INTERVAL 1 DAY)");
        $c->execute([$msg_hash]);
        if ($c->fetchColumn() > 0) { $errors[] = 'You already sent this same message. Please wait for the administrator to reply.'; }
        $c = $pdo->prepare("SELECT COUNT(*) FROM support_requests WHERE email = ? AND created_at > (NOW() - INTERVAL 1 DAY)");
        $c->execute([$email]);
        if ($c->fetchColumn() >= 3) { $errors[] = 'You have already sent 3 requests today. Please wait for the administrator to answer them.'; }
        $c = $pdo->prepare("SELECT COUNT(*) FROM support_requests WHERE ip_hash = ? AND created_at > (NOW() - INTERVAL 1 HOUR)");
        $c->execute([$ip_hash]);
        if ($c->fetchColumn() >= 5) { $errors[] = 'Too many requests from this connection. Please try again later.'; }
    }
    if (!$errors) {
        try {
            $verified = ($mode === 'suspension' || $me) ? 1 : 0;
            $uid = $mode === 'suspension' ? (int)$ctx['user_id'] : ($me ? (int)$me['id'] : null);
            $pdo->prepare("INSERT INTO support_requests (user_id, name, email, subject, message, category, is_verified, status, ip_hash, message_hash)
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?)")
                ->execute([$uid, $name ?: null, $email, $subject, $message, $mode, $verified, $ip_hash, $msg_hash]);
            $rid = (int)$pdo->lastInsertId();
            foreach ($pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll(PDO::FETCH_COLUMN) as $aid) {
                notify($aid, 'New support request', ($mode === 'suspension' ? 'Suspended account: ' : '') . $subject . ' - from ' . $email, 'admin/support-view.php?id=' . $rid);
            }
            if ($mode === 'suspension') { unset($_SESSION['suspended_contact']); }
            flash('success', 'Your request has been sent to the administrator. Please wait for a reply.');
            redirect($mode === 'suspension' ? 'login.php' : 'contact.php');
        } catch (PDOException $ex) {
            error_log('Support request failed: ' . $ex->getMessage());
            $errors[] = 'Sorry, your request could not be saved right now. Please try again in a few minutes.';
        }
    }
}

$page_title = $mode === 'suspension' ? 'Contact Administrator' : 'Contact Us';
require __DIR__ . '/includes/header.php';
?>
<main class="container my-4 flex-grow-1">
    <div class="row justify-content-center"><div class="col-12 col-md-9 col-lg-7">
        <div class="card shadow p-4">
            <h3 class="mb-1"><?= $mode === 'suspension' ? 'Contact Administrator' : 'Contact Us' ?></h3>
            <p class="text-muted"><?= $mode === 'suspension'
                ? 'Your account is suspended. Explain the situation below and an administrator will review your request. You do not need to log in.'
                : 'Questions, problems or feedback? Send us a message and the administrator will get back to you.' ?></p>
            <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
            <form method="POST" class="needs-validation" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="type" value="<?= e($mode) ?>">
                <div style="position:absolute;left:-9999px" aria-hidden="true"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <?php if ($mode === 'general' && !$me): ?>
                    <div class="mb-3"><label class="form-label" for="name">Your name (optional)</label>
                        <input type="text" id="name" name="name" class="form-control" maxlength="100" value="<?= e($name) ?>"></div>
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <input type="email" id="email" name="email" class="form-control" required maxlength="150" value="<?= e($email) ?>"
                           <?= ($mode === 'suspension' || $me) ? 'readonly' : '' ?>>
                    <?php if ($mode === 'suspension'): ?><div class="form-text text-success">This is the email of your suspended account (verified).</div>
                    <?php elseif (!$me): ?><div class="invalid-feedback">Please enter a valid email address.</div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" class="form-control" required minlength="3" maxlength="150" value="<?= e($subject) ?>"
                           <?= $mode === 'suspension' ? 'readonly' : '' ?>>
                    <div class="invalid-feedback">Please enter a subject.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="message">Message</label>
                    <textarea id="message" name="message" class="form-control" rows="6" required minlength="10" maxlength="2000"
                              placeholder="<?= $mode === 'suspension' ? 'Explain why your account should be reviewed...' : 'Write your message here...' ?>"><?= e($message) ?></textarea>
                    <div class="invalid-feedback">Please write at least 10 characters.</div>
                </div>
                <button type="submit" class="btn btn-primary">Submit Request</button>
                <a href="<?= url($mode === 'suspension' ? 'login.php' : 'index.php') ?>" class="btn btn-link"><?= $mode === 'suspension' ? 'Back to Login' : 'Cancel' ?></a>
            </form>
        </div>
    </div></div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
