<?php
/** Order / service-request tracking for buyers and providers (admin sees all, read-only). */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['member', 'freelancer', 'advertiser', 'admin']);
$uid = (int)$user['id'];

// Allowed status moves, enforced on the server: [current status][who] => new statuses
const ORDER_FLOW = [
    'requested' => ['provider' => ['accepted', 'cancelled'], 'buyer' => ['cancelled']],
    'accepted'  => ['provider' => ['completed', 'cancelled'], 'buyer' => []],
];
if (is_post()) {
    csrf_verify();
    $o = fetch_one('SELECT o.*, s.provider_id, s.title FROM orders o JOIN services s ON s.id=o.service_id WHERE o.id=?', [(int)($_POST['order_id'] ?? 0)]);
    $new = (string)($_POST['status'] ?? '');
    $who = !$o ? null : ((int)$o['provider_id'] === $uid ? 'provider' : ((int)$o['buyer_id'] === $uid ? 'buyer' : null));
    if ($o && $who && in_array($new, ORDER_FLOW[$o['status']][$who] ?? [], true)) {
        q('UPDATE orders SET status=? WHERE id=? AND status=?', [$new, $o['id'], $o['status']]);
        notify((int)($who === 'provider' ? $o['buyer_id'] : $o['provider_id']), 'Order #' . $o['id'] . ' ("' . $o['title'] . '") is now ' . $new . '.', 'services/orders.php');
        audit_log('order_status', "Order #{$o['id']} -> $new");
        flash('success', 'Order updated.');
    } else { flash('error', 'That change is not allowed.'); }
    redirect('services/orders.php');
}

$cols = 'o.*, s.title, s.provider_id, b.full_name AS buyer, p.full_name AS provider';
$join = 'FROM orders o JOIN services s ON s.id=o.service_id JOIN users b ON b.id=o.buyer_id JOIN users p ON p.id=s.provider_id';
$sections = [];
if ($user['role'] === 'admin') { $sections['All orders'] = fetch_all("SELECT $cols $join ORDER BY o.id DESC LIMIT 100"); }
else {
    $sections['Requests I made'] = fetch_all("SELECT $cols $join WHERE o.buyer_id=? ORDER BY o.id DESC LIMIT 50", [$uid]);
    if ($user['role'] === 'freelancer') { $sections['Requests for my services'] = fetch_all("SELECT $cols $join WHERE s.provider_id=? ORDER BY o.id DESC LIMIT 50", [$uid]); }
}
$pageTitle = 'Orders';
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <div class="page-head"><div><h1>Orders</h1><p>Track service requests from request to completion.</p></div><a class="btn btn-outline" href="<?= e(url('services/index.php')) ?>">Browse services</a></div>
  <?php foreach ($sections as $title => $rows): ?>
    <h2 style="margin-top:1.2rem"><?= e($title) ?></h2>
    <div class="table-wrap"><table><thead><tr><th>#</th><th>Service</th><th>Buyer</th><th>Provider</th><th class="num">Price</th><th>Status</th><th>Action</th></tr></thead><tbody>
      <?php if (!$rows): ?><tr><td colspan="7"><div class="empty"><p>No orders yet.</p></div></td></tr><?php endif; ?>
      <?php foreach ($rows as $o): $who = (int)$o['provider_id'] === $uid ? 'provider' : ((int)$o['buyer_id'] === $uid ? 'buyer' : null); $moves = $who ? (ORDER_FLOW[$o['status']][$who] ?? []) : []; ?>
        <tr><td><?= (int)$o['id'] ?></td><td><?= e($o['title']) ?><?= $o['note'] ? '<br><small>' . e($o['note']) . '</small>' : '' ?></td>
          <td><?= e($o['buyer']) ?></td><td><?= e($o['provider']) ?></td><td class="num"><?= e(money($o['price'])) ?></td><td><?= status_badge($o['status']) ?></td>
          <td><div class="actions"><?php foreach ($moves as $m): ?>
            <form method="post" data-confirm="Set order to <?= e($m) ?>?"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="status" value="<?= e($m) ?>">
              <button class="btn btn-sm <?= $m === 'cancelled' ? 'btn-danger' : 'btn-ok' ?>" type="submit"><?= e(ucfirst($m === 'accepted' ? 'accept' : ($m === 'completed' ? 'complete' : 'cancel'))) ?></button></form>
          <?php endforeach; ?><?= $moves ? '' : '-' ?></div></td></tr>
      <?php endforeach; ?></tbody></table></div>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
