<?php
// register.php - Customer / Vendor registration
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) { redirect(dashboard_for($_SESSION['role'])); }

$error = '';
$success = '';
$selected_ids = [];
$types_grouped = service_types_grouped();
$old = ['name' => '', 'email' => '', 'phone' => '', 'role' => 'customer', 'address' => '', 'business_description' => ''];

if (is_post()) {
    csrf_verify();
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $role     = $_POST['role'] ?? 'customer';
    $address  = trim($_POST['address'] ?? '');
    $about    = trim($_POST['business_description'] ?? '');
    $type_ids = clean_service_type_ids($_POST['service_types'] ?? []);   // only ids that really exist, no duplicates
    $selected_ids = $type_ids;
    if (!in_array($role, ['customer', 'vendor'], true)) { $role = 'customer'; }   // nobody can self-register as admin
    $old = ['name' => $name, 'email' => $email, 'phone' => $phone, 'role' => $role, 'address' => $address, 'business_description' => $about];

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Please fill out all mandatory fields.';
    } elseif (mb_strlen($name) > 100) {
        $error = 'Name is too long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
        $error = 'Please enter a valid phone number.';
    } elseif ($role === 'vendor' && $phone === '') {
        $error = 'Vendors must provide a contact phone number.';
    } elseif ($role === 'vendor' && mb_strlen($address) < 10) {
        $error = 'Vendors must provide the business address (at least 10 characters).';
    } elseif ($role === 'vendor' && !$type_ids) {
        $error = 'Please select at least one service that you offer.';
    } elseif ($role === 'vendor' && mb_strlen($about) > 1000) {
        $error = 'The business description is too long (maximum 1000 characters).';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email is already registered!';
        } else {
            $status = ($role === 'vendor') ? 'pending' : 'active';      // vendors cannot log in until the admin approves them
            $saved = false;
            try {
                $pdo->beginTransaction();                                // user + selected services are saved together or not at all
                $pdo->prepare("INSERT INTO users (name, email, password, role, phone, address, business_description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $phone ?: null,
                               $role === 'vendor' ? $address : null, ($role === 'vendor' && $about !== '') ? $about : null, $status]);
                $new_id = (int)$pdo->lastInsertId();
                if ($role === 'vendor') { save_vendor_service_types($new_id, $type_ids); }
                $pdo->commit();
                $saved = true;
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                error_log('Registration failed: ' . $ex->getMessage());
                $error = 'Sorry, the registration could not be saved. Please try again.';
            }
            if ($saved) {
                if ($role === 'vendor') {
                    $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($admins as $aid) {
                        notify($aid, 'New vendor registration', $name . ' has registered as a vendor and is waiting for approval.', 'admin/vendors.php?status=pending');
                    }
                    $success = 'Vendor registration submitted! You can log in only after the admin approves your account.';
                } else {
                    $success = 'Registration successful! You can now log in.';
                }
                $selected_ids = [];
                $old = ['name' => '', 'email' => '', 'phone' => '', 'role' => 'customer', 'address' => '', 'business_description' => ''];
            }
        }
    }
}

$page_title = 'Register';
require __DIR__ . '/includes/header.php';
?>
<main class="container my-4 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-7 col-lg-5">
            <div class="card shadow p-4">
                <h3 class="text-center mb-4">Create an Account</h3>
                <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= e($success) ?> <a href="<?= url('login.php') ?>" class="alert-link">Login</a></div>
                <?php endif; ?>
                <form action="<?= url('register.php') ?>" method="POST" class="needs-validation" novalidate id="regForm">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="role">Register As</label>
                        <select id="role" name="role" class="form-select">
                            <option value="customer" <?= $old['role'] === 'customer' ? 'selected' : '' ?>>Customer</option>
                            <option value="vendor" <?= $old['role'] === 'vendor' ? 'selected' : '' ?>>Vendor (needs admin approval)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="name" id="nameLabel">Full Name</label>
                        <input type="text" id="name" name="name" class="form-control" maxlength="100" required value="<?= e($old['name']) ?>">
                        <div class="invalid-feedback">Please enter the name.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" required value="<?= e($old['email']) ?>">
                        <div class="invalid-feedback">Please enter a valid email address.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="phone" id="phoneLabel">Phone (optional)</label>
                        <input type="tel" id="phone" name="phone" class="form-control" pattern="[0-9+\-\s()]{7,20}" value="<?= e($old['phone']) ?>">
                        <div class="invalid-feedback">Enter a valid phone number.</div>
                    </div>

                    <div id="vendorFields" class="border rounded p-3 mb-3 bg-light" style="display:none">
                        <p class="small fw-bold mb-2">Business details (vendors only)</p>
                        <div class="mb-3">
                            <label class="form-label" for="address">Business address</label>
                            <textarea id="address" name="address" class="form-control" rows="2" minlength="10"><?= e($old['address']) ?></textarea>
                            <div class="invalid-feedback">Please enter the business address (at least 10 characters).</div>
                        </div>
                        <?php require __DIR__ . '/includes/service_types_field.php'; ?>
                        <div class="mt-3 mb-0">
                            <label class="form-label" for="business_description">About your business (optional)</label>
                            <textarea id="business_description" name="business_description" class="form-control" rows="2" maxlength="1000"
                                      placeholder="A short description customers will see on your profile"><?= e($old['business_description']) ?></textarea>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <label class="form-label" for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" minlength="6" required>
                            <div class="invalid-feedback">At least 6 characters.</div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label class="form-label" for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="6" required>
                            <div class="invalid-feedback">Please confirm your password.</div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success w-100">Register</button>
                </form>
                <p class="text-center mt-3 mb-0">Already have an account? <a href="<?= url('login.php') ?>">Login here</a></p>
            </div>
        </div>
    </div>
</main>
<script>
(function () {
    var role = document.getElementById('role'), box = document.getElementById('vendorFields');
    function sync() {
        var v = role.value === 'vendor';
        box.style.display = v ? 'block' : 'none';
        ['address', 'phone'].forEach(function (id) { document.getElementById(id).required = v; });
        document.getElementById('nameLabel').textContent = v ? 'Business Name' : 'Full Name';
        document.getElementById('phoneLabel').textContent = v ? 'Contact Phone' : 'Phone (optional)';
    }
    role.addEventListener('change', sync); sync();
    // vendors must tick at least one service type
    document.getElementById('regForm').addEventListener('submit', function (e) {
        var err = document.getElementById('typesError');
        if (role.value === 'vendor' && !document.querySelector('.service-type-cb:checked')) {
            e.preventDefault(); err.style.display = 'block';
            document.getElementById('serviceTypesBox').scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else { err.style.display = 'none'; }
    });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
