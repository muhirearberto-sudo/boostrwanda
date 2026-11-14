<?php
/** Platform settings + recent audit log. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['admin']);
$errors = [];
if (is_post()) {
    csrf_verify();
    $minD = parse_amount($_POST['min_deposit'] ?? ''); $maxD = parse_amount($_POST['max_deposit'] ?? ''); $minW = parse_amount($_POST['min_withdrawal'] ?? '');
    if ($minD === null || $maxD === null || $minW === null) { $errors[] = 'All amounts must be positive numbers.'; }
    elseif ((float)$minD > (float)$maxD) { $errors[] = 'Minimum deposit cannot exceed maximum deposit.'; }
    if (!$errors) {
        $vals = ['min_deposit' => $minD, 'max_deposit' => $maxD, 'min_withdrawal' => $minW,
                 'registration_open' => isset($_POST['registration_open']) ? '1' : '0', 'sandbox_gateway_enabled' => isset($_POST['sandbox_gateway_enabled']) ? '1' : '0'];
        foreach ($vals as $k => $v) { q('INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)', [$k, $v]); }
        audit_log('settings_updated', json_encode($vals));
        flash('success', 'Settings saved.');
        redirect('admin/settings.php');
    }
}
$logs = fetch_all('SELECT a.*, u.full_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 40');
$pageTitle = 'Settings';
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <?= admin_subnav('settings') ?>
  <div class="page-head"><div><h1>Platform settings</h1></div></div>
  <div class="card">
    <?php if ($errors): ?><ul class="error-list"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <form method="post"><?= csrf_field() ?>
      <div class="form-row"><div class="form-group"><label for="min_deposit">Minimum deposit</label><input id="min_deposit" name="min_deposit" value="<?= e($_POST['min_deposit'] ?? setting('min_deposit')) ?>" required></div>
        <div class="form-group"><label for="max_deposit">Maximum deposit</label><input id="max_deposit" name="max_deposit" value="<?= e($_POST['max_deposit'] ?? setting('max_deposit')) ?>" required></div></div>
      <div class="form-group"><label for="min_withdrawal">Minimum withdrawal</label><input id="min_withdrawal" name="min_withdrawal" value="<?= e($_POST['min_withdrawal'] ?? setting('min_withdrawal')) ?>" required></div>
      <div class="form-group"><label><input type="checkbox" name="registration_open" style="width:auto" <?= setting('registration_open', '1') === '1' ? 'checked' : '' ?>> Registration open</label></div>
      <div class="form-group"><label><input type="checkbox" name="sandbox_gateway_enabled" style="width:auto" <?= setting('sandbox_gateway_enabled', '1') === '1' ? 'checked' : '' ?>> Sandbox payment gateway enabled (demo deposits)</label></div>
      <button class="btn btn-primary" type="submit">Save settings</button>
    </form></div>
  <div class="card"><h2>Audit log (latest 40)</h2>
    <div class="table-wrap"><table><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead><tbody>
      <?php foreach ($logs as $l): ?><tr><td><?= e(fmt_date($l['created_at'])) ?></td><td><?= e($l['full_name'] ?? 'system') ?></td><td><?= e($l['action']) ?></td><td><small><?= e($l['details']) ?></small></td><td><small><?= e($l['ip_address']) ?></small></td></tr><?php endforeach; ?></tbody></table></div></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
