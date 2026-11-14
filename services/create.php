<?php
/** Create / edit a service. Freelancers manage their own; admins may edit any. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['freelancer', 'admin']);
$uid = (int)$user['id'];
$id = (int)($_GET['id'] ?? 0);
$svc = $id ? fetch_one('SELECT * FROM services WHERE id=?', [$id]) : null;
if ($id && (!$svc || ($user['role'] !== 'admin' && (int)$svc['provider_id'] !== $uid))) { http_response_code(403); flash('error', 'You cannot edit this service.'); redirect('services/index.php'); }
$errors = [];

if (is_post()) {
    csrf_verify();
    $title = clean($_POST['title'] ?? '', 150); $desc = clean($_POST['description'] ?? '', 3000);
    $cat = (int)($_POST['category_id'] ?? 0); $price = parse_amount($_POST['price'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active';
    if (mb_strlen($title) < 5) { $errors[] = 'Title must be at least 5 characters.'; }
    if (mb_strlen($desc) < 20) { $errors[] = 'Description must be at least 20 characters.'; }
    if (!fetch_val("SELECT id FROM categories WHERE id=? AND type='service'", [$cat])) { $errors[] = 'Choose a valid category.'; }
    if ($price === null || (float)$price > 10000000) { $errors[] = 'Enter a valid price.'; }
    if (!$errors) {
        if ($svc) { q('UPDATE services SET title=?, description=?, category_id=?, price=?, status=? WHERE id=?', [$title, $desc, $cat, $price, $status, $id]); audit_log('service_updated', "Service #$id"); }
        else { q('INSERT INTO services (provider_id, category_id, title, description, price, status) VALUES (?,?,?,?,?,?)', [$uid, $cat, $title, $desc, $price, $status]); audit_log('service_created', $title); }
        flash('success', 'Service saved.');
        redirect($user['role'] === 'admin' ? 'admin/services.php' : 'services/index.php');
    }
}
$v = fn($k, $d = '') => e($_POST[$k] ?? ($svc[$k] ?? $d));
$pageTitle = $svc ? 'Edit service' : 'New service';
include __DIR__ . '/../includes/header.php';
?>
<div class="container mid"><div class="page-head"><h1><?= e($pageTitle) ?></h1></div>
  <div class="card">
    <?php if ($errors): ?><ul class="error-list"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <form method="post"><?= csrf_field() ?>
      <div class="form-group"><label for="title">Title</label><input id="title" name="title" value="<?= $v('title') ?>" required maxlength="150"></div>
      <div class="form-group"><label for="description">Description</label><textarea id="description" name="description" required maxlength="3000"><?= $v('description') ?></textarea></div>
      <div class="form-row">
        <div class="form-group"><label for="category_id">Category</label><select id="category_id" name="category_id">
          <?php foreach (categories_of('service') as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)($_POST['category_id'] ?? ($svc['category_id'] ?? 0)) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="price">Price (<?= e(app_config()['currency']) ?>)</label><input id="price" name="price" inputmode="decimal" value="<?= $v('price') ?>" required></div>
      </div>
      <div class="form-group"><label for="status">Status</label><select id="status" name="status">
        <?php foreach (['active', 'inactive'] as $s): ?><option value="<?= $s ?>" <?= ($_POST['status'] ?? ($svc['status'] ?? 'active')) === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
      <button class="btn btn-primary" type="submit">Save service</button> <a class="btn btn-light" href="<?= e(url('services/index.php')) ?>">Cancel</a>
    </form></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
