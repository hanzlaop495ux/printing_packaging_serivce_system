<?php
// index.php - Main landing page
require_once __DIR__ . '/includes/bootstrap.php';

$categories = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM services s JOIN users u ON s.vendor_id = u.id
                           WHERE s.category_id = c.id AND s.is_available = 1 AND u.status = 'active') AS cnt
                           FROM categories c ORDER BY c.type, c.name")->fetchAll();
$featured = $pdo->query("SELECT s.*, u.name AS vendor_name, c.name AS category_name
                         FROM services s JOIN users u ON s.vendor_id = u.id LEFT JOIN categories c ON s.category_id = c.id
                         WHERE s.is_available = 1 AND u.status = 'active' ORDER BY s.created_at DESC, s.id DESC LIMIT 3")->fetchAll();

$page_title = 'Custom Printing & Packaging Solutions';
require __DIR__ . '/includes/header.php';
?>
<header class="hero text-center py-5">
    <div class="container py-3">
        <h1 class="display-4 fw-bold">Custom Printing &amp; Packaging Solutions</h1>
        <p class="lead">Get custom banners, brochures, business cards, and boxes delivered straight to your door from multiple verified vendors.</p>
        <?php if (is_logged_in()): ?>
            <a href="<?= url(dashboard_for($_SESSION['role'])) ?>" class="btn btn-light btn-lg mt-3">Go to my Dashboard</a>
        <?php else: ?>
            <a href="<?= url('register.php') ?>" class="btn btn-light btn-lg mt-3">Get Started Today</a>
            <a href="<?= url('customer/services.php') ?>" class="btn btn-outline-light btn-lg mt-3 ms-2">Browse Services</a>
        <?php endif; ?>
    </div>
</header>

<section class="container my-5">
    <div class="row text-center g-4">
        <div class="col-md-4">
            <div class="card h-100 shadow-sm p-3 card-hover">
                <h3 class="h4">Multi-Vendor Choices</h3>
                <p class="mb-0">Compare prices, styles, and turnaround times from various packaging and printing vendors, and order from several vendors in a single checkout.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 shadow-sm p-3 card-hover">
                <h3 class="h4">Custom Uploads &amp; Pricing</h3>
                <p class="mb-0">Upload your own PDF/image designs and get automated cost calculations based on size, quantity, customization and delivery distance.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 shadow-sm p-3 card-hover">
                <h3 class="h4">Order Tracking on Map</h3>
                <p class="mb-0">Follow your order's delivery progress on an integrated map, with status updates and an estimated vehicle position.</p>
            </div>
        </div>
    </div>
</section>

<section class="container mb-5">
    <h2 class="text-center page-title mb-4">Popular Categories</h2>
    <div class="d-flex flex-wrap justify-content-center gap-2">
        <?php foreach ($categories as $c): ?>
            <a class="btn btn-outline-primary rounded-pill" href="<?= url('customer/services.php?category=' . (int)$c['id']) ?>">
                <?= e($c['name']) ?> <span class="badge bg-primary"><?= (int)$c['cnt'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($featured): ?>
<section class="container mb-5">
    <h2 class="text-center page-title mb-4">Latest Services</h2>
    <div class="row g-4">
        <?php foreach ($featured as $s): ?>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm card-hover service-card">
                    <div class="card-body">
                        <span class="badge bg-info text-dark mb-2"><?= e($s['category_name'] ?? 'General') ?></span>
                        <h5 class="card-title"><?= e($s['title']) ?></h5>
                        <p class="text-muted small mb-2">by <?= e($s['vendor_name']) ?></p>
                        <p class="price mb-0"><?= money($s['price']) ?> <small class="text-muted fs-6">/ <?= e($s['unit_label']) ?></small></p>
                    </div>
                    <div class="card-footer bg-white border-0 pb-3">
                        <a href="<?= url('customer/service-details.php?id=' . (int)$s['id']) ?>" class="btn btn-outline-primary btn-sm">View Details</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="container mb-5">
    <h2 class="text-center page-title mb-4">How It Works</h2>
    <div class="row text-center g-3">
        <div class="col-6 col-md-3"><div class="fs-1">1</div><strong>Choose services</strong><p class="small text-muted">Browse &amp; search, and add items from one or many vendors to one cart.</p></div>
        <div class="col-6 col-md-3"><div class="fs-1">2</div><strong>Upload design</strong><p class="small text-muted">Attach your PDF or image and pick customisations.</p></div>
        <div class="col-6 col-md-3"><div class="fs-1">3</div><strong>Order &amp; pay</strong><p class="small text-muted">Cash on Delivery by default, online optional.</p></div>
        <div class="col-6 col-md-3"><div class="fs-1">4</div><strong>Track on map</strong><p class="small text-muted">Follow the estimated delivery progress until it arrives.</p></div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
