<?php
// help.php - Help & Support centre (public)
require_once __DIR__ . '/includes/bootstrap.php';
$page_title = 'Help & Support';
require __DIR__ . '/includes/header.php';
?>
<main class="container flex-grow-1 mb-4" style="max-width:900px">
    <h2 class="page-title mb-3">Help &amp; Support</h2>
    <div class="row g-3 mb-4">
        <div class="col-md-6"><div class="card shadow-sm p-3 h-100"><h5>How do I place an order?</h5>
            <ol class="small mb-2"><li>Create a customer account and log in.</li><li>Open <a href="<?= url('customer/services.php') ?>">Browse Services</a> and add services to your cart (you can mix several vendors).</li>
            <li>Upload your design (PDF or image) and check out.</li><li>Pick your delivery location on the map and confirm.</li></ol></div></div>
        <div class="col-md-6"><div class="card shadow-sm p-3 h-100"><h5>How do I track my order?</h5>
            <p class="small mb-2">Open <a href="<?= url('customer/my-orders.php') ?>">My Orders</a>, choose an order and click <strong>Track</strong>. You see the status of every vendor's part and an estimated delivery position on the map.</p></div></div>
        <div class="col-md-6"><div class="card shadow-sm p-3 h-100"><h5>Payments</h5>
            <p class="small mb-2">Cash on Delivery is the default. Online card / wallet is a demonstration only. See the <a href="<?= url('faq.php') ?>">FAQs</a>.</p></div></div>
        <div class="col-md-6"><div class="card shadow-sm p-3 h-100"><h5>Still need help?</h5>
            <p class="small mb-2">Send a message to the administrator. If your account is suspended, try to log in first: the login page then shows a <strong>Contact Administrator</strong> button.</p>
            <a class="btn btn-primary btn-sm align-self-start" href="<?= url('contact.php') ?>">Contact Us</a></div></div>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
