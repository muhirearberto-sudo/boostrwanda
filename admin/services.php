<?php
/** Admin service management: activate / deactivate, edit. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['admin']);
if (is_post()) {
    csrf_verify();
    $id = (int)($_POST['service_id'] ?? 0); $to = ($_POST['status'] ?? '') === 'active' ? 'active' : 'inactive';
    q('UPDATE services SET status=? WHERE id=?', [$to, $id]);
    audit_log('service_status', "Service #$id set to $to");
    flash('success', "Service #$id is now $to.");
    redirect('admin/services.php');
}
$pg = paginate((int)fetch_val('SELECT COUNT(*) FROM services'), 12);
$rows = fetch_all("SELECT s.*, c.name category, u.full_name provider, (SELECT COUNT(*) FROM orders o WHERE o.service_id=s.id) orders_n
                   FROM services s JOIN categories c ON c.id=s.category_id JOIN users u ON u.id=s.provider_id ORDER BY s.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}");
$pageTitle = 'Manage services';
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <?= admin_subnav('services') ?>
  <div class="page-head"><div><h1>Services</h1></div><a class="btn btn-primary" href="<?= e(url('services/create.php')) ?>">+ New service</a></div>
  <div class="table-wrap"><table><thead><tr><th>#</th><th>Service</th><th>Provider</th><th>Category</th><th class="num">Price</th><th>Orders</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    <?php if (!$rows): ?><tr><td colspan="8"><div class="empty"><p>No services yet.</p></div></td></tr><?php endif; ?>
    <?php foreach ($rows as $s): ?><tr><td><?= (int)$s['id'] ?></td><td><a href="<?= e(url('services/details.php?id=' . $s['id'])) ?>"><?= e($s['title']) ?></a></td><td><?= e($s['provider']) ?></td><td><?= e($s['category']) ?></td>
      <td class="num"><?= e(number_format((float)$s['price'], 2)) ?></td><td><?= (int)$s['orders_n'] ?></td><td><?= status_badge($s['status']) ?></td>
      <td><div class="actions"><a class="btn btn-outline btn-sm" href="<?= e(url('services/create.php?id=' . $s['id'])) ?>">Edit</a>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="service_id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="status" value="<?= $s['status'] === 'active' ? 'inactive' : 'active' ?>"><button class="btn btn-light btn-sm" type="submit"><?= $s['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button></form></div></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?= pagination_html($pg) ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
