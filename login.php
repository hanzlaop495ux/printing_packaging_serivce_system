<?php
// login.php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) { redirect(dashboard_for($_SESSION['role'])); }

$error = '';
$show_contact = false;
$next  = $_GET['next'] ?? ($_POST['next'] ?? '');

if (is_post()) {
    csrf_verify();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email !== '' && $password !== '') {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'suspended') {
                $error = 'Your account has been suspended. Please contact the administrator.';
                // The password was correct, so this person really owns the account: remember it for the contact form (this is NOT a login)
                $_SESSION['suspended_contact'] = ['user_id' => (int)$user['id'], 'email' => $user['email'], 'name' => $user['name'],
                                                  'role' => $user['role'], 'time' => time()];
                $show_contact = true;
            } elseif ($user['role'] === 'vendor' && $user['status'] === 'pending') {
                $error = 'Your vendor account is waiting for admin approval. You can log in and use the vendor dashboard once the admin approves it.';
            } else {
                login_user($user);
                // Only follow "next" if it is a local path inside this project
                $go = dashboard_for($user['role']);
                if ($next !== '' && $next[0] === '/' && substr($next, 0, 2) !== '//' && strpos($next, "\n") === false
                    && ($user['role'] !== 'admin' || strpos($next, '/admin/') !== false)
                    && strpos($next, '/login.php') === false) {
                    header('Location: ' . $next);
                    exit;
                }
                redirect($go);
            }
        } else {
            $error = 'Invalid email or password!';
        }
    } else {
        $error = 'Please fill in all fields!';
    }
}

$page_title = 'Login';
require __DIR__ . '/includes/header.php';
?>
<main class="container my-4 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-6 col-lg-5">
            <div class="card shadow p-4">
                <h3 class="text-center mb-4">Account Login</h3>
                <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?>
                    <?php if ($show_contact): ?><div class="mt-2"><a href="<?= url('contact.php?type=suspension') ?>" class="btn btn-warning btn-sm fw-semibold">Contact Administrator</a></div><?php endif; ?>
                </div><?php endif; ?>
                <form action="<?= url('login.php') ?>" method="POST" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="next" value="<?= e($next) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>">
                        <div class="invalid-feedback">Please enter a valid email address.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                        <div class="invalid-feedback">Please enter your password.</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
                <p class="text-center mt-3 mb-1">Don't have an account? <a href="<?= url('register.php') ?>">Register here</a></p>
                <p class="text-center small mb-0"><a href="<?= url('admin/login.php') ?>" class="text-muted">Admin login</a></p>
            </div>
        </div>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
