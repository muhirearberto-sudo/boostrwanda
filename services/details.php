<?php
/** Service details + "request service" (creates an order record the provider can accept). */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['member', 'freelancer', 'advertiser', 'admin']);
$uid = (int)$user['id'];
$id = (int)($_GET['id'] ?? 0);
$s = fetch_one('SELECT s.*, c.name AS category, u.full_name AS provider, u.member_id FROM services s
                JOIN categories c ON c.id=s.category_id JOIN users u ON u.id=s.provider_id WHERE s.id=?', [$id]);
if (!$s || ($s['status'] !== 'active' && $user['role'] !== 'admin' && (int)$s['provider_id'] !== $uid)) { http_response_code(404); flash('error', 'Service not found.'); redirect('services/index.php'); }
$isOwner = (int)$s['provider_id'] === $uid;

if (is_post()) {
    csrf_verify();
    if ($user['role'] === 'admin' || $isOwner) { flash('error', 'You cannot order this service.'); redirect('services/details.php?id=' . $id); }
    if ($s['status'] !== 'active') { flash('error', 'This service is not available.'); redirect('services/index.php'); }
    $note = clean($_POST['note'] ?? '', 500);
    q('INSERT INTO orders (service_id, buyer_id, note, price) VALUES (?,?,?,?)', [$id, $uid, $note ?: null, $s['price']]);   // price is copied from DB, not from the form
    notify((int)$s['provider_id'], $user['full_name'] . ' requested your service "' . $s['title'] . '".', 'services/orders.php');
    audit_log('order_created', "Order for service #$id");
    flash('success', 'Request sent to the provider. Track it under Orders.');
    redirect('services/orders.php');
}
$pageTitle = $s['title'];
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <p style="margin-top:1.2rem"><a href="<?= e(url('services/index.php')) ?>">&larr; All services</a></p>
  <div class="detail-grid">
    <div class="card"><div class="meta" style="margin-bottom:.6rem"><span class="badge"><?= e($s['category']) ?></span><?= status_badge($s['status']) ?></div>
      <h1><?= e($s['title']) ?></h1><p class="pre"><?= e($s['description']) ?></p></div>
    <aside><div class="card">
      <div class="price" style="font-size:1.8rem"><?= e(money($s['price'])) ?></div>
      <dl class="kv" style="margin:1rem 0"><dt>Provider</dt><dd><?= e($s['provider']) ?> (<?= e($s['member_id']) ?>)</dd></dl>
      <?php if ($isOwner || $user['role'] === 'admin'): ?>
        <a class="btn btn-outline btn-block" href="<?= e(url('services/create.php?id=' . $id)) ?>">Edit service</a>
      <?php else: ?>
        <form method="post"><?= csrf_field() ?>
          <div class="form-group"><label for="note">Message to provider <small>(optional)</small></label><textarea id="note" name="note" maxlength="500" style="min-height:80px"></textarea></div>
          <button class="btn btn-primary btn-block" type="submit">Request this service</button></form>
        <p class="help">Payment between buyer and provider is arranged directly; wallet escrow is not part of this version.</p>
        <a class="btn btn-light btn-block" style="margin-top:.5rem" href="<?= e(url('messages/index.php?with=' . (int)$s['provider_id'])) ?>">Message provider</a>
      <?php endif; ?>
    </div></aside>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
