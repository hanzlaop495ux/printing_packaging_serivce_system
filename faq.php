<?php
// faq.php - Frequently asked questions (public)
require_once __DIR__ . '/includes/bootstrap.php';
$faqs = [
  ['Can I order from more than one vendor at the same time?', 'Yes. Add services from different vendors to one cart and check out once. The order is split into one part per vendor; each vendor prepares and delivers its own part and has its own delivery charge.'],
  ['How is the price calculated?', 'For every item: (service price + selected options) x quantity. Delivery is charged per vendor: a base fee plus a rate per kilometre from the vendor to your delivery location. You see the total before you confirm.'],
  ['Which payment methods are available?', 'Cash on Delivery is the default; it stays Pending until your order is delivered and the vendor confirms the cash was received. Online card / wallet is a demonstration only: no real payment is made.'],
  ['What do the order statuses mean?', 'Pending (waiting for the vendor), Processing (being printed or packed), Dispatched (with the delivery rider), Delivered, or Cancelled.'],
  ['Is the map tracking live GPS?', 'No. The truck position on the map is an estimate based on the dispatch time and the distance. The status and rider details come from the vendor.'],
  ['Which design files can I upload?', 'PDF, PNG, JPG, GIF or WEBP up to 10 MB. You can attach a design when you order or later from the order details page.'],
  ['Can I cancel an order?', 'Yes, as long as every vendor part is still Pending. After a vendor starts processing, please contact support.'],
  ['I am a vendor. Why can I not log in yet?', 'New vendor accounts must be approved by the administrator first. If your account was suspended, try to log in once: you will see a Contact Administrator button to send your request.'],
];
$page_title = 'FAQs';
require __DIR__ . '/includes/header.php';
?>
<main class="container flex-grow-1 mb-4" style="max-width:860px">
    <h2 class="page-title mb-3">Frequently Asked Questions</h2>
    <div class="accordion shadow-sm" id="faqList">
        <?php foreach ($faqs as $i => $f): ?>
            <div class="accordion-item">
                <h3 class="accordion-header"><button class="accordion-button <?= $i ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>" aria-expanded="<?= $i ? 'false' : 'true' ?>" aria-controls="faq<?= $i ?>"><?= e($f[0]) ?></button></h3>
                <div id="faq<?= $i ?>" class="accordion-collapse collapse <?= $i ? '' : 'show' ?>" data-bs-parent="#faqList"><div class="accordion-body"><?= e($f[1]) ?></div></div>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="mt-3">Did not find your answer? <a href="<?= url('contact.php') ?>">Contact us</a>.</p>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
